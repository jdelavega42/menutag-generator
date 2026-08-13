<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\MenuTagParameters;
use App\Enums\FaceContent;
use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuTagRequest;
use App\Http\Resources\MenuTagResource;
use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /api/v1/menu-tags (docs/openapi.yaml, Sanctum-only — the guest flow lives
 * on the web UI, never here). Thin by design: StoreMenuTagRequest validates
 * (three levels, contract 02), the Policy authorizes, GenerateMenuTagJob does
 * the async work; this class only wires them together.
 */
class MenuTagController extends Controller
{
    private const float EPS = 1e-9;

    /**
     * GET /api/v1/menu-tags — paginated list of the caller's own records
     * (forOwner scope), filterable by status and preset.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var array{per_page?: int, filter?: array{status?: string, preset?: string}} $validated */
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', Rule::enum(MenuTagStatus::class)],
            'filter.preset' => ['sometimes', Rule::enum(Preset::class)],
        ], [
            'per_page.integer' => 'Il parametro per_page deve essere un numero intero.',
            'per_page.min' => 'Il parametro per_page deve essere almeno :min.',
            'per_page.max' => 'Il parametro per_page non può superare :max.',
            'filter.status.Illuminate\Validation\Rules\Enum' => 'Stato non valido: usa queued, processing, completed oppure failed.',
            'filter.preset.Illuminate\Validation\Rules\Enum' => 'Formato non valido: usa menutag, coaster oppure coin_cart.',
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = MenuTag::query()
            ->forOwner($user)
            ->latest('created_at')
            ->latest('id');

        if (isset($validated['filter']['status'])) {
            $query->where('status', $validated['filter']['status']);
        }

        if (isset($validated['filter']['preset'])) {
            $query->where('preset', $validated['filter']['preset']);
        }

        return MenuTagResource::collection(
            $query->paginate($validated['per_page'] ?? 15)->appends($request->query()),
        );
    }

    /**
     * POST /api/v1/menu-tags — validate, create the record with status
     * `queued`, dispatch the async job and answer 202 immediately (spec
     * §5.4): the client polls GET /menu-tags/{id} for the outcome.
     *
     * Rate limiting: throttle:api-generate on the route (30/h per user,
     * config product.api.generations_per_hour, registered by WS-5).
     */
    public function store(StoreMenuTagRequest $request): JsonResponse
    {
        $parameters = $request->toParameters();
        $preset = $request->presetEnum() ?? Preset::MenuTag;

        /** @var User $user */
        $user = $request->user();

        $this->assertUsableLogo($user, $parameters);

        $menuTag = MenuTag::create([
            'user_id' => $user->id,
            'guest_token' => null,
            'label' => $request->validated('label'),
            'preset' => $preset,
            'customized' => $this->isCustomized($preset, $parameters),
            'logo_asset_id' => $parameters->logoAssetId,
            'parameters' => $parameters,
            'status' => MenuTagStatus::Queued,
        ]);

        GenerateMenuTagJob::dispatch($menuTag->id);

        return MenuTagResource::make($menuTag)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/menu-tags/{id} — status and metadata, Policy-gated: 403
     * when the record belongs to another user, 404 when it does not exist.
     */
    public function show(MenuTag $menuTag): MenuTagResource
    {
        Gate::authorize('view', $menuTag);

        return MenuTagResource::make($menuTag);
    }

    /**
     * The Form Request validates only the SHAPE of logo_asset_id (WS-1
     * boundary): existence and ownership are authorization concerns, checked
     * here through LogoAssetPolicy::use. Reported as a 422 on the same field
     * so API clients get one consistent error shape.
     */
    private function assertUsableLogo(User $user, MenuTagParameters $parameters): void
    {
        if ($parameters->logoAssetId === null) {
            return;
        }

        $logo = LogoAsset::query()->find($parameters->logoAssetId);

        if ($logo === null || ! Gate::forUser($user)->allows('use', $logo)) {
            throw ValidationException::withMessages([
                'parameters.logo_asset_id' => 'Il logo indicato non esiste o non appartiene alla tua libreria: '
                    .'carica il logo con POST /api/v1/logos e usa l\'id restituito.',
            ]);
        }
    }

    /**
     * `customized` flag for API-created records. The web UI sets it when the
     * user unlocks "personalizza questo formato"; the API has no lock, so the
     * flag is derived: the record is customized when any parameter deviates
     * from the preset defaults (config product.presets.*.defaults).
     *
     * Preset-coherent deviations do NOT count (documented choice):
     * - content payloads (qr_data_front/back, logo_asset_id): filling the
     *   preset's faces with your own URL/logo IS the preset's purpose;
     * - a face that equals the default plus a centered logo (qr → qr_logo):
     *   "QR + logo opzionale" is the declared MenuTag behavior (spec §5.2);
     * - `size` on dynamic-floor presets when it equals the computed floor
     *   max(default, size_min_qr(URL)) — the preset size is calculated, not
     *   fixed (spec §5.2);
     * - `plate`: the N-piece plate is a production option offered on every
     *   preset (spec §5.2), not a parameter unlock.
     */
    private function isCustomized(Preset $preset, MenuTagParameters $parameters): bool
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('product.presets.'.$preset->value.'.defaults');
        $dynamicFloor = (bool) config('product.presets.'.$preset->value.'.size_is_dynamic_floor', false);
        $snapshot = $parameters->toArray();

        $ignored = ['qr_data_front', 'qr_data_back', 'logo_asset_id', 'plate'];

        foreach ($defaults as $key => $default) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $value = $snapshot[$key] ?? null;

            if ($key === 'size' && $dynamicFloor) {
                if (abs((float) $value - $this->dynamicFloorSize((float) $default, $parameters)) > self::EPS) {
                    return true;
                }

                continue;
            }

            if (in_array($key, ['front', 'back'], true) && $this->isFaceEquivalent((string) $default, (string) $value)) {
                continue;
            }

            if ($key === 'layer_height') {
                // Null means "printer profile default" (contract 02): compare
                // the RESOLVED value, so omitting the field is not a deviation.
                if (abs($parameters->resolvedLayerHeight() - (float) $default) > self::EPS) {
                    return true;
                }

                continue;
            }

            $matches = (is_float($default) || is_int($default)) && is_numeric($value)
                ? abs((float) $default - (float) $value) <= self::EPS
                : $default === $value;

            if (! $matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * The calculated preset size (spec §5.2): max(floor, size_min_qr(URL))
     * for the front URL, rounded up to 0.1 mm by minSizeForQr itself.
     */
    private function dynamicFloorSize(float $floor, MenuTagParameters $parameters): float
    {
        if (! $parameters->front->hasQr() || $parameters->qrDataFront === null) {
            return $floor;
        }

        $minForUrl = MenuTagParameters::minSizeForQr(
            $parameters->qrDataFront,
            $parameters->qrEc,
            $parameters->shape,
        );

        return max($floor, $minForUrl ?? $floor);
    }

    /**
     * A face equals its preset default also when it only ADDS the optional
     * centered logo (qr → qr_logo, none → logo stays a deviation).
     */
    private function isFaceEquivalent(string $default, string $actual): bool
    {
        if ($default === $actual) {
            return true;
        }

        return $default === FaceContent::Qr->value && $actual === FaceContent::QrLogo->value;
    }
}

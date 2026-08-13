/**
 * MenuTag 3D viewer module (contract 04).
 *
 * Exports createMenuTagViewer(el, initialParams, productConfig) which returns
 * an object with:
 * - update(params): parametric preview — smooth geometry rebuilt entirely
 *   client-side, ZERO server requests;
 * - loadStl(url, accentUrl?): loads the real STL(s) produced by the engine;
 * - dispose(): mandatory in Alpine's destroy() — geometries, materials,
 *   textures and the WebGL renderer are released (browsers cap WebGL
 *   contexts at ~16; without cleanup the viewer silently stops rendering).
 *
 * The QR symbol is generated client-side with the `qrcode` npm library,
 * forcing BYTE MODE, the requested EC level and the minimum version computed
 * from the SAME capacity table as PHP and the Python engine (values injected
 * from config/product.php — parity, no duplicated constants). The initial
 * preview therefore shows a real, scannable QR from the first paint.
 */

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import QRCode from 'qrcode';
import { minQrVersion, modulesForVersion } from './menutag/qr-rules.js';

const BODY_COLOR = 0xe7e2d9;      // neutral matte PLA look
const ACCENT_COLOR = 0x1f2937;    // inlay accent / STL accent part
const ENGRAVE_COLOR = 0x8a8579;   // engraved modules read by shadow
const CANVAS_PX = 1024;

export function createMenuTagViewer(el, initialParams, productConfig) {
    const state = {
        params: { ...initialParams },
        disposed: false,
        rafId: null,
        rebuildQueued: false,
        showingStl: false,
    };

    // --- Scene ------------------------------------------------------------
    const scene = new THREE.Scene();

    const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 4000);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    el.appendChild(renderer.domElement);
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    renderer.domElement.style.display = 'block';

    scene.add(new THREE.HemisphereLight(0xffffff, 0x666666, 1.1));
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
    keyLight.position.set(60, -90, 140);
    scene.add(keyLight);
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
    fillLight.position.set(-80, 60, 60);
    scene.add(fillLight);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;

    const modelGroup = new THREE.Group();
    scene.add(modelGroup);

    const resize = () => {
        const width = el.clientWidth || 1;
        const height = el.clientHeight || 1;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height, false);
    };

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(el);
    resize();

    const animate = () => {
        if (state.disposed) {
            return;
        }

        state.rafId = requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    };

    // --- Helpers ----------------------------------------------------------

    const disposeObject = (object) => {
        object.traverse((node) => {
            if (node.geometry) {
                node.geometry.dispose();
            }

            const materials = Array.isArray(node.material) ? node.material : [node.material];

            for (const material of materials) {
                if (!material) {
                    continue;
                }

                if (material.map) {
                    material.map.dispose();
                }

                material.dispose();
            }
        });
    };

    const clearModel = () => {
        for (const child of [...modelGroup.children]) {
            modelGroup.remove(child);
            disposeObject(child);
        }
    };

    const fitCamera = (radius) => {
        const distance = Math.max(radius, 10) * 2.6;

        if (camera.position.length() < 1) {
            camera.position.set(0.6, -1, 0.85);
        }

        camera.position.setLength(distance);
        controls.target.set(0, 0, 0);
        controls.update();
    };

    const outlineShape = (params, inset = 0) => {
        const shape = new THREE.Shape();

        if (params.shape === 'circle') {
            const radius = Math.max(1, params.size / 2 - inset);
            shape.absarc(0, 0, radius, 0, Math.PI * 2, false);

            return shape;
        }

        const half = Math.max(1, params.size / 2 - inset);
        const fillet = Math.min(Math.max(0, (params.fillet || 0) - inset), half - 0.01);
        const k = half - fillet;

        shape.moveTo(-k, -half);
        shape.lineTo(k, -half);
        if (fillet > 0) shape.absarc(k, -k, fillet, -Math.PI / 2, 0, false);
        shape.lineTo(half, k);
        if (fillet > 0) shape.absarc(k, k, fillet, 0, Math.PI / 2, false);
        shape.lineTo(-k, half);
        if (fillet > 0) shape.absarc(-k, k, fillet, Math.PI / 2, Math.PI, false);
        shape.lineTo(-half, -k);
        if (fillet > 0) shape.absarc(-k, -k, fillet, Math.PI, Math.PI * 1.5, false);
        shape.closePath();

        return shape;
    };

    /**
     * QR matrix in forced byte mode / EC / minimum version — the same rule
     * as config (parity with PHP and the engine). Returns null when the
     * payload does not fit any version up to 20.
     */
    const qrMatrix = (data, ec) => {
        const version = minQrVersion(data, ec, productConfig.qr);

        if (version === null) {
            return null;
        }

        try {
            const code = QRCode.create([{ data, mode: 'byte' }], {
                errorCorrectionLevel: ec,
                version,
            });

            return code.modules;
        } catch {
            return null;
        }
    };

    const drawLogoPlaceholder = (ctx, cx, cy, sizePx, color) => {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = sizePx * 0.06;
        ctx.beginPath();
        ctx.arc(cx, cy, sizePx * 0.44, 0, Math.PI * 2);
        ctx.stroke();
        ctx.font = `bold ${Math.round(sizePx * 0.34)}px sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('LOGO', cx, cy);
        ctx.restore();
    };

    /**
     * Face texture: a transparent canvas mapped 1:1 on the face bounding
     * square (size mm → CANVAS_PX px). The QR occupies the same proportion
     * it will have on the printed piece: pitch = usable / (n + 8) on squares
     * and usable / (n·√2 + 8) on circles (symbol inscribed on the diagonal).
     */
    const buildFaceTexture = (params, content, data, logoImage) => {
        const canvas = document.createElement('canvas');
        canvas.width = CANVAS_PX;
        canvas.height = CANVAS_PX;
        const ctx = canvas.getContext('2d');
        const pxPerMm = CANVAS_PX / params.size;
        const center = CANVAS_PX / 2;
        const inkColor = params.mode === 'inlay' ? '#111827' : '#8a8579';

        if (content === 'qr' || content === 'qr_logo') {
            const matrix = qrMatrix(data || productConfig.qr.demo_url, params.qrEc);

            if (matrix) {
                const n = matrix.size;
                const usable = params.baseProfile === 'rimmed'
                    ? params.size - 2 * (params.rimWidth || 0)
                    : params.size;
                const pitchMm = params.shape === 'square'
                    ? usable / (n + 8)
                    : usable / (n * Math.SQRT2 + 8);
                const modulePx = pitchMm * pxPerMm;
                const originPx = center - (n * modulePx) / 2;

                ctx.fillStyle = inkColor;

                for (let row = 0; row < n; row++) {
                    for (let col = 0; col < n; col++) {
                        if (matrix.get(row, col)) {
                            ctx.fillRect(
                                originPx + col * modulePx,
                                originPx + row * modulePx,
                                modulePx + 0.5,
                                modulePx + 0.5,
                            );
                        }
                    }
                }

                if (content === 'qr_logo') {
                    const holeSize = n * modulePx * 0.28;
                    ctx.fillStyle = '#f6f4ef';
                    ctx.fillRect(center - holeSize / 2, center - holeSize / 2, holeSize, holeSize);

                    if (logoImage) {
                        ctx.drawImage(logoImage, center - holeSize * 0.42, center - holeSize * 0.42, holeSize * 0.84, holeSize * 0.84);
                    } else {
                        drawLogoPlaceholder(ctx, center, center, holeSize * 0.8, inkColor);
                    }
                }
            }
        } else if (content === 'logo') {
            const areaPx = CANVAS_PX * 0.55;

            if (logoImage) {
                const ratio = Math.min(areaPx / logoImage.width, areaPx / logoImage.height);
                const w = logoImage.width * ratio;
                const h = logoImage.height * ratio;
                ctx.drawImage(logoImage, center - w / 2, center - h / 2, w, h);
            } else {
                drawLogoPlaceholder(ctx, center, center, areaPx, inkColor);
            }
        }

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = 4;

        return texture;
    };

    let logoImage = null;
    let logoImageUrl = null;

    const ensureLogoImage = (url, onReady) => {
        if (!url || url === logoImageUrl) {
            onReady();

            return;
        }

        const image = new Image();
        image.onload = () => {
            logoImage = image;
            logoImageUrl = url;
            onReady();
        };
        image.onerror = () => onReady();
        image.src = url;
    };

    /** Smooth parametric body: flat slab or rimmed (slab + drip rim ring). */
    const buildParametric = () => {
        clearModel();
        state.showingStl = false;

        const params = state.params;
        const bodyMaterial = new THREE.MeshStandardMaterial({
            color: BODY_COLOR,
            roughness: 0.85,
            metalness: 0.0,
        });

        const rimmed = params.baseProfile === 'rimmed';
        const recess = rimmed ? Math.min(params.recessDepth || 0, params.thickness - 0.2) : 0;
        const slabHeight = params.thickness - recess;

        const slab = new THREE.Mesh(
            new THREE.ExtrudeGeometry(outlineShape(params), {
                depth: slabHeight,
                bevelEnabled: false,
                curveSegments: 96,
            }),
            bodyMaterial,
        );
        modelGroup.add(slab);

        if (rimmed) {
            const rimShape = outlineShape(params);
            rimShape.holes.push(new THREE.Path(outlineShape(params, params.rimWidth || 0).getPoints(96).reverse()));

            const rim = new THREE.Mesh(
                new THREE.ExtrudeGeometry(rimShape, {
                    depth: recess,
                    bevelEnabled: false,
                    curveSegments: 96,
                }),
                bodyMaterial,
            );
            rim.position.z = slabHeight;
            modelGroup.add(rim);
        }

        const faceZ = rimmed ? slabHeight + 0.05 : params.thickness + 0.05;

        const addFacePlane = (content, data, z, flip) => {
            if (!content || content === 'none') {
                return;
            }

            const texture = buildFaceTexture(params, content, data, logoImage);
            const material = new THREE.MeshBasicMaterial({
                map: texture,
                transparent: true,
                side: THREE.FrontSide,
            });
            const plane = new THREE.Mesh(new THREE.PlaneGeometry(params.size, params.size), material);
            plane.position.z = z;

            if (flip) {
                plane.rotation.y = Math.PI; // back face graphic, mirrored by the engine
            }

            modelGroup.add(plane);
        };

        addFacePlane(params.front, params.qrDataFront, faceZ, false);
        addFacePlane(params.back, params.qrDataBack, -0.05, true);

        modelGroup.position.z = -params.thickness / 2;
        fitCamera(params.size / 2 + params.thickness);
    };

    const queueRebuild = () => {
        if (state.rebuildQueued || state.disposed) {
            return;
        }

        state.rebuildQueued = true;
        requestAnimationFrame(() => {
            state.rebuildQueued = false;

            if (!state.disposed) {
                ensureLogoImage(state.params.logoPreviewUrl, () => {
                    if (!state.disposed) {
                        buildParametric();
                    }
                });
            }
        });
    };

    // --- Public API ---------------------------------------------------------

    const api = {
        /** Parametric preview update — client-side only, no server requests. */
        update(params) {
            state.params = { ...state.params, ...params };
            queueRebuild();
        },

        /** Load the real STL(s) produced by the engine (base + inlay accent). */
        async loadStl(url, accentUrl = null) {
            const loader = new STLLoader();

            try {
                const geometry = await loader.loadAsync(url);
                let accentGeometry = null;

                if (accentUrl) {
                    try {
                        accentGeometry = await loader.loadAsync(accentUrl);
                    } catch {
                        accentGeometry = null;
                    }
                }

                if (state.disposed) {
                    geometry.dispose();
                    accentGeometry?.dispose();

                    return;
                }

                clearModel();
                state.showingStl = true;

                geometry.computeBoundingBox();
                const center = new THREE.Vector3();
                geometry.boundingBox.getCenter(center);

                const base = new THREE.Mesh(
                    geometry,
                    new THREE.MeshStandardMaterial({ color: BODY_COLOR, roughness: 0.8 }),
                );
                base.position.sub(center);
                modelGroup.add(base);

                if (accentGeometry) {
                    const accent = new THREE.Mesh(
                        accentGeometry,
                        new THREE.MeshStandardMaterial({ color: ACCENT_COLOR, roughness: 0.6 }),
                    );
                    accent.position.sub(center);
                    modelGroup.add(accent);
                }

                modelGroup.position.z = 0;
                geometry.computeBoundingSphere();
                fitCamera(geometry.boundingSphere?.radius ?? 60);
            } catch {
                // Network/parse failure: keep the parametric preview alive.
                if (!state.showingStl) {
                    queueRebuild();
                }
            }
        },

        /** Full cleanup — mandatory in Alpine destroy() (contract 04). */
        dispose() {
            if (state.disposed) {
                return;
            }

            state.disposed = true;

            if (state.rafId !== null) {
                cancelAnimationFrame(state.rafId);
            }

            resizeObserver.disconnect();
            clearModel();
            controls.dispose();
            renderer.dispose();
            renderer.domElement.remove();
        },
    };

    buildParametric();
    animate();

    return api;
}

export default createMenuTagViewer;

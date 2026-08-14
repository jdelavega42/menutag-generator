{{-- Stylized QR tile (design mockups, Fase 0): the bicolor echo of the
     product — light base in currentColor, dark "engraving" in surface-0. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 22" {{ $attributes }}>
    <rect x="1" y="1" width="20" height="20" rx="5" fill="currentColor"/>
    <g fill="var(--surface-0)">
        <rect x="4" y="4" width="5" height="5"/>
        <rect x="13" y="4" width="5" height="5"/>
        <rect x="4" y="13" width="5" height="5"/>
        <rect x="13" y="13" width="2" height="2"/>
        <rect x="16" y="13" width="2" height="2"/>
        <rect x="13" y="16" width="2" height="2"/>
        <rect x="16" y="16" width="2" height="2"/>
    </g>
</svg>

<?php
/**
 * Inline SVG icon set (Lucide-style, 24×24, 1.75 stroke).
 * Consistent stroke width and geometry across the whole site — never emoji.
 *
 *   echo icon('plane', 'card__icon-svg');
 */

function icon(string $name, string $class = '', int $size = 24): string
{
    static $paths = null;

    if ($paths === null) {
        $paths = [
            // Navigation & UI
            'menu'        => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'home'        => '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
            'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'close'       => '<path d="M18 6 6 18M6 6l12 12"/>',
            'arrow-right' => '<path d="M5 12h14m-7-7 7 7-7 7"/>',
            'arrow-left'  => '<path d="M19 12H5m7 7-7-7 7-7"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
            'check'       => '<path d="M20 6 9 17l-5-5"/>',
            'check-circle'=> '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
            'alert'       => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>',
            'info'        => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/>',
            'star'        => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" stroke="none"/>',
            'external'    => '<path d="M15 3h6v6m0-6L10 14M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
            'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
            'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'filter'      => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
            'edit'        => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'trash'       => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'eye'         => '<path d="M2 12s3.64-7 10-7 10 7 10 7-3.64 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'list'        => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'inbox'       => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'tag'         => '<path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.82 8.82a2 2 0 0 0 2.82 0l7.18-7.18a2 2 0 0 0 0-2.82z"/><path d="M7 7h.01"/>',

            // Services
            'plane'       => '<path d="M17.8 19.2 16 11l3.5-3.5a2.12 2.12 0 0 0-3-3L13 8 4.8 6.2a1 1 0 0 0-.9 1.7l4.6 3.3-2.4 2.4-2.3-.6a1 1 0 0 0-1 1.6l2.6 2.6 2.6 2.6a1 1 0 0 0 1.6-1l-.6-2.3 2.4-2.4 3.3 4.6a1 1 0 0 0 1.7-.9z"/>',
            'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'crown'       => '<path d="M2 18h20l-2-11-5 4-3-6-3 6-5-4z"/><path d="M2 18v2a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2"/>',
            'sparkles'    => '<path d="M12 3 13.6 8.4 19 10l-5.4 1.6L12 17l-1.6-5.4L5 10l5.4-1.6z"/><path d="M18 4.5 18.6 6.4 20.5 7l-1.9.6L18 9.5l-.6-1.9L15.5 7l1.9-.6z"/>',
            'moon'        => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>',
            'route'       => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
            'map-pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
            'map'         => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
            'navigation'  => '<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
            'key'         => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3"/>',

            // Vehicles
            'car'         => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/><path d="M9.5 17h5M5 12h14"/>',
            'suv'         => '<path d="M3 17v-6l2.5-5h13L21 11v6h-2"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/><path d="M9.5 17h5M3 11h18M9 6v5M15 6v5"/>',
            'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'luggage'     => '<rect x="5" y="7" width="14" height="14" rx="2"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M10 12v5M14 12v5"/>',
            'wifi'        => '<path d="M5 12.55a11 11 0 0 1 14 0M8.5 16.4a6 6 0 0 1 7 0M2 8.82a15 15 0 0 1 20 0"/><path d="M12 20h.01"/>',
            'droplet'     => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5S12.5 4 12 2c-.5 2-2 4-4 5.5S5 13 5 15a7 7 0 0 0 7 7z"/>',
            'snowflake'   => '<path d="M12 2v20M4.9 4.9l14.2 14.2M2 12h20M19.1 4.9 4.9 19.1"/>',

            // Trust
            'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'shield-check'=> '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
            'award'       => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>',
            'headset'     => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
            'sparkle-clean' => '<path d="M9.94 14.06 3 21m6.94-6.94L14 10m-4.06 4.06L6 10l4-4 4.06 4.06M14 10l7-7"/><path d="M18 14l1 3 3 1-3 1-1 3-1-3-3-1 3-1z"/>',

            // Contact
            'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            // Official WhatsApp glyph (single filled path, so it stays
            // recognisable at any size and inherits currentColor).
            'whatsapp'    => '<path fill="currentColor" stroke="none" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/>',
            'facebook'    => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
            'instagram'   => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
            'x-twitter'   => '<path d="M18.9 2H22l-7.1 8.1L23.2 22h-6.5l-5.1-6.6L5.8 22H2.7l7.6-8.7L1.5 2H8l4.6 6.1z" fill="currentColor" stroke="none"/>',
            'linkedin'    => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
        ];
    }

    if (!isset($paths[$name])) {
        // Unknown icon → neutral dot, never a broken render.
        $paths[$name] = '<circle cx="12" cy="12" r="9"/>';
    }

    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        e($class), $size, $size, $paths[$name]
    );
}

/**
 * Elegant fallback artwork for vehicles with no uploaded photo.
 * Renders a gold line-art silhouette rather than a broken image.
 */
function vehicle_placeholder_svg(string $class_label = ''): string
{
    $isSedan = stripos($class_label, 'sedan') !== false;

    $sedan = '<path d="M15 128h-9a4 4 0 0 1-4-4v-24a12 12 0 0 1 7-11l24-10 18-16a16 16 0 0 1 11-4h60a16 16 0 0 1 11 4l18 16 24 10a12 12 0 0 1 7 11v24a4 4 0 0 1-4 4h-9"/>'
           . '<path d="M52 63h96"/><path d="M100 63v-19"/>'
           . '<path d="M2 100h196"/>'
           . '<circle cx="52" cy="128" r="17"/><circle cx="148" cy="128" r="17"/>'
           . '<path d="M69 128h62"/>';

    $suv   = '<path d="M12 130H6a4 4 0 0 1-4-4V96a10 10 0 0 1 6-9l22-9 16-26a16 16 0 0 1 13-7h74a16 16 0 0 1 13 7l16 26 22 9a10 10 0 0 1 6 9v30a4 4 0 0 1-4 4h-6"/>'
           . '<path d="M42 78h116"/><path d="M100 52v26"/>'
           . '<path d="M2 104h196"/>'
           . '<circle cx="50" cy="130" r="18"/><circle cx="150" cy="130" r="18"/>'
           . '<path d="M68 130h64"/>';

    return sprintf(
        '<svg viewBox="0 0 200 150" fill="none" stroke="currentColor" '
        . 'stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">%s</svg>',
        $isSedan ? $sedan : $suv
    );
}

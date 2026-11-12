<?php
/**
 * Etichetta leggibile di un tag.
 * Nei contenuti l'array `tags` contiene gli slug (packet-tracer, wifi, o-cms):
 * il nome da mostrare si legge dal file data/tags/{slug}.json. Se il file non
 * esiste si ripiega sullo slug ripulito, così il tema non dipende mai dai dati.
 */
if (!function_exists('flavor_tag_label')) {
    function flavor_tag_label(string $tag): string {
        static $cache = [];
        $slug = ocms_slug($tag);
        if ($slug === '') {
            return $tag;
        }
        if (!array_key_exists($slug, $cache)) {
            $name = '';
            $file = ocms_data_path('tags/' . $slug . '.json');
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data) && !empty($data['name']) && is_string($data['name'])) {
                    $name = $data['name'];
                }
            }
            $cache[$slug] = $name !== '' ? $name : ucfirst(str_replace('-', ' ', $slug));
        }
        return $cache[$slug];
    }
}

<?php

namespace App\Application;

class SampleData
{
    public static function items(): array
    {
        $path = base_path('../app/data/mercado_seed.json');

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $items = json_decode($contents, true);

        return is_array($items) ? $items : [];
    }
}

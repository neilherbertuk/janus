<?php

if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        return rtrim(getcwd(), '\/') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('implode_recur')) {
    function implode_recur($separator, $array)
    {
        $output = "";

        foreach ($array as $item) {
            if (is_array($item)) {
                $output .= implode_recur($separator, $item); // Recursive array
            } else {
                $output .= $separator . $item;
            }
        }
        return $output;
    }
}

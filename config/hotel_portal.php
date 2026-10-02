<?php

$languages = array_values(
    array_filter(
        preg_split(
            '/\s+/',
            trim(
                <<<'LANGUAGES'
aa ab ae af ak am an ar as av ay az
ba be bg bh bi bm bn bo br bs
ca ce ch co cr cs cu cv cy
da de dv dz
ee el en eo es et eu
fa ff fi fj fo fr fy
ga gd gl gn gu gv
ha he hi ho hr ht hu hy hz
ia id ie ig ii ik io is it iu
ja jv
ka kg ki kj kk kl km kn ko kr ks ku kv kw ky
la lb lg li ln lo lt lu lv
mg mh mi mk ml mn mr ms mt my
na nb nd ne ng nl nn no nr nv ny
oc oj om or os
pa pi pl ps pt
qu
rm rn ro ru rw
sa sc sd se sg si sk sl sm sn so sq sr ss st su sv sw
ta te tg th ti tk tl tn to tr ts tt tw ty
ug uk ur uz
ve vi vo
wa wo
xh
yi yo
za zh zu
LANGUAGES
            )
        )
    )
);

$config = [
    'languages' =>
        $languages,

    /*
     * Every ISO 639-1 language is selectable.
     * Languages without a built-in dictionary
     * use English unless the Hotel provides
     * a custom translation override.
     */
    'default_enabled_languages' =>
        $languages,

    'built_in_locales' => [
        'en',
        'ar',
        'bn',
        'hi',
        'ur',
        'ne',
        'ml',
        'ta',
        'tl',
        'zh',
        'fr',
        'es',
        'ru',
        'tr',
        'fa',
        'id',
    ],
];

/*
 * HOTEL_PORTAL_ALL_LANGUAGES_DEFAULT_V4
 */
$config['default_enabled_locales'] =
    array_values(
        array_keys(
            $config['languages'] ?? []
        )
    );

return $config;

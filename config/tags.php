<?php

return [
    /*
     * The given function will be used to generate slugs.
     * Defaults to Str::slug()
     */
    'slugger' => null,

    /*
     * The fully qualified class name of the tag model.
     */
    'tag_model' => Spatie\Tags\Tag::class,

    /*
     * The name of the table to create.
     */
    'table_name' => 'tags',
    
    /*
     * Disable translations to use simple string tags
     */
    'translatable' => false,
];
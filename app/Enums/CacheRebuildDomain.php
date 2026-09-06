<?php

namespace App\Enums;

enum CacheRebuildDomain: string
{
    case Products = 'products';
    case Blog = 'blog';
}

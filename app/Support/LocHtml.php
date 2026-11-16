<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Lọc HTML bài viết (CKEditor) trước khi lưu: chỉ giữ thẻ định dạng an toàn,
 * loại script/sự kiện/iframe/style để chống XSS. Nội dung hiển thị bằng {!! !!}
 * nên BẮT BUỘC đi qua bộ lọc này ở controller.
 */
class LocHtml
{
    public static function lamSach(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');

        $thuMucCache = storage_path('framework/cache/purifier');
        if (! is_dir($thuMucCache)) {
            mkdir($thuMucCache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $thuMucCache);

        $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,s,ul,ol,li,h2,h3,h4,blockquote,pre,code,a[href|title|target],img[src|alt|width|height],table,thead,tbody,tr,th,td');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.AutoParagraph', false);

        return (new HTMLPurifier($config))->purify($html);
    }
}
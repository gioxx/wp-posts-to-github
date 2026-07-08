<?php

namespace POTOGH;

class ExportStatus
{
    public const NEVER_EXPORTED = 'never_exported';
    public const EXPORTED = 'exported';
    public const MODIFIED_SINCE_EXPORT = 'modified_since_export';

    public static function determine(?string $exportedAtGmt, string $postModifiedGmt): string
    {
        if (empty($exportedAtGmt)) {
            return self::NEVER_EXPORTED;
        }

        if (strtotime($postModifiedGmt) > strtotime($exportedAtGmt)) {
            return self::MODIFIED_SINCE_EXPORT;
        }

        return self::EXPORTED;
    }
}

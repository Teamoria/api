<?php

namespace App\Enums;

enum FileCategory: string
{
    case VIDEO = 'video';
    case IMAGE = 'image';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';

    public static function fromMimeType(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'video/') => self::VIDEO,
            str_starts_with($mime, 'image/') => self::IMAGE,
            str_starts_with($mime, 'audio/') => self::AUDIO,
            default => self::DOCUMENT,
        };
    }
}
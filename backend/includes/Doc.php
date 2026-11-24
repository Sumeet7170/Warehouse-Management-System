<?php

final class Doc
{
    public static function next(string $prefix): string
    {
        $stamp = date('Ymd');
        $key = $prefix . '-' . $stamp;
        Database::exec(
            'INSERT INTO document_sequences (seq_key, last_value) VALUES (?, 1) ON DUPLICATE KEY UPDATE last_value = last_value + 1',
            [$key]
        );
        $n = (int)Database::value('SELECT last_value FROM document_sequences WHERE seq_key = ?', [$key]);
        return sprintf('%s-%s-%04d', $prefix, $stamp, $n);
    }
}

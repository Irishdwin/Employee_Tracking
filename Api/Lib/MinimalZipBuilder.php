<?php
/**
 * Minimal ZIP archive builder (store-only) — fallback when PHP zip extension is unavailable.
 */
declare(strict_types=1);

final class MinimalZipBuilder
{
    /** @var array<int,array{name:string,content:string,offset:int,crc:int,size:int}> */
    private array $entries = [];

    public function addString(string $name, string $content): void
    {
        $crc = crc32($content);
        if ($crc < 0) {
            $crc += 0x100000000;
        }

        $this->entries[] = [
            'name' => str_replace('\\', '/', $name),
            'content' => $content,
            'offset' => 0,
            'crc' => $crc,
            'size' => strlen($content),
        ];
    }

    public function build(): string
    {
        $data = '';
        $central = '';
        $offset = 0;

        foreach ($this->entries as &$entry) {
            $name = $entry['name'];
            $content = $entry['content'];
            $crc = $entry['crc'];
            $size = $entry['size'];
            $entry['offset'] = $offset;

            $local = pack('V', 0x04034b50)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', strlen($name))
                . pack('v', 0)
                . $name
                . $content;

            $data .= $local;
            $offset += strlen($local);

            $central .= pack('V', 0x02014b50)
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', strlen($name))
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $entry['offset'])
                . $name;
        }
        unset($entry);

        $centralSize = strlen($central);
        $end = pack('V', 0x06054b50)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', count($this->entries))
            . pack('v', count($this->entries))
            . pack('V', $centralSize)
            . pack('V', $offset)
            . pack('v', 0);

        return $data . $central . $end;
    }
}

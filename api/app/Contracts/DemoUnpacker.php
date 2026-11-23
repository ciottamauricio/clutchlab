<?php

namespace App\Contracts;

use App\Storage\DemoUnpackFailed;

// Some platforms hand out demos compressed (FACEIT's room download is a .dem.zst). Storage only
// ever holds a plain .dem, because "watch in game" downloads it for CS2's playdemo, which can't
// read a compressed file — so a packed upload is unpacked before it is stored.
interface DemoUnpacker
{
    /**
     * Whether the file is packed in a format this unpacker opens, judged by its first bytes, not
     * its name: a .zst saved as .dem is still packed.
     */
    public function handles(string $path): bool;

    /**
     * Unpack the file into a new temporary file and return its path; the caller deletes it.
     *
     * @throws DemoUnpackFailed with code demo.invalid (unreadable) or demo.unpacked_too_large
     */
    public function unpack(string $path): string;
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qbank_importquestions;

use core_text;

/**
 * Helper class for question import.
 *
 * @package    qbank_importquestions
 * @copyright  2026 Phung Vo Anh <phung.voanh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** Maximum byte length of a filename component on commonly used filesystems. */
    public const MAX_TEMP_FILENAME_BYTES = 255;

    /**
     * Get a safe filename for temporary files on the filesystem.
     *
     * Ensures the filename does not exceed the filesystem limit, preserving the extension when it fits
     * and truncating only at multi-byte UTF-8 character boundaries.
     *
     * @param string $filename Original filename.
     * @return string Safe filename suitable for filesystem operations.
     */
    public static function get_safe_import_filename(string $filename): string {
        if (strlen($filename) <= self::MAX_TEMP_FILENAME_BYTES) {
            return $filename;
        }

        $extensionposition = strrpos($filename, '.');
        if ($extensionposition !== false && $extensionposition > 0) {
            $ext = substr($filename, $extensionposition);
            $maxbaselen = self::MAX_TEMP_FILENAME_BYTES - strlen($ext);
            if ($maxbaselen > 0) {
                $basename = substr($filename, 0, $extensionposition);
                return core_text::str_max_bytes($basename, $maxbaselen) . $ext;
            }
        }

        return core_text::str_max_bytes($filename, self::MAX_TEMP_FILENAME_BYTES);
    }
}

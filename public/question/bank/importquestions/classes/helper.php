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

/**
 * Helper class for question import.
 *
 * @package    qbank_importquestions
 * @copyright  2026 Phung Vo Anh <phung.voanh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Get a name for the temporary import file that is safe for the filesystem.
     *
     * The user's filename is not used, as it may exceed the filesystem limit. A short sanitised
     * extension is kept, as some formats (e.g. Blackboard .dat) rely on it.
     *
     * @param string $realfilename The original uploaded filename.
     * @return string Temporary filename, e.g. 'import.xml'.
     */
    public static function get_temp_import_filename(string $realfilename): string {
        $extension = clean_param(pathinfo($realfilename, PATHINFO_EXTENSION), PARAM_ALPHANUM);
        $extension = $extension !== '' ? '.' . substr($extension, 0, 10) : '';
        return 'import' . $extension;
    }
}

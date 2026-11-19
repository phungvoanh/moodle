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
 * Unit tests for question import helper.
 *
 * @package    qbank_importquestions
 * @copyright  2026 Phung Vo Anh <phung.voanh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qbank_importquestions\helper::class)]
final class helper_test extends \basic_testcase {
    /**
     * Test filename that is well within the 255-byte limit is returned unchanged.
     */
    public function test_get_safe_import_filename_short_filename(): void {
        $filename = 'simple_question.xml';
        $this->assertSame($filename, helper::get_safe_import_filename($filename));
    }

    /**
     * Test ASCII filename exactly at 255 bytes is returned unchanged.
     */
    public function test_get_safe_import_filename_at_limit(): void {
        $filename = str_repeat('a', 251) . '.xml';
        $this->assertSame(helper::MAX_TEMP_FILENAME_BYTES, strlen($filename));
        $this->assertSame($filename, helper::get_safe_import_filename($filename));
    }

    /**
     * Test ASCII filename exceeding 255 bytes is safely truncated preserving extension.
     */
    public function test_get_safe_import_filename_ascii_over_limit(): void {
        $filename = str_repeat('a', 300) . '.xml';
        $safename = helper::get_safe_import_filename($filename);

        $this->assertLessThanOrEqual(helper::MAX_TEMP_FILENAME_BYTES, strlen($safename));
        $this->assertSame(helper::MAX_TEMP_FILENAME_BYTES, strlen($safename));
        $this->assertStringEndsWith('.xml', $safename);
        $this->assertSame(str_repeat('a', 251) . '.xml', $safename);
    }

    /**
     * Test long Cyrillic filename exceeding 255 bytes (MDL-90156).
     */
    public function test_get_safe_import_filename_cyrillic_over_limit(): void {
        // The Cyrillic base has 11 characters and 22 bytes in UTF-8.
        // 13 repetitions = 143 characters and 286 bytes (+ 4 bytes for '.xml' = 290 bytes).
        $filename = str_repeat('ВопросТеста', 13) . '.xml';
        $this->assertLessThanOrEqual(helper::MAX_TEMP_FILENAME_BYTES, \core_text::strlen($filename));
        $this->assertGreaterThan(helper::MAX_TEMP_FILENAME_BYTES, strlen($filename));

        $safename = helper::get_safe_import_filename($filename);

        $this->assertLessThanOrEqual(helper::MAX_TEMP_FILENAME_BYTES, strlen($safename));
        $this->assertSame(
            \core_text::str_max_bytes(str_repeat('ВопросТеста', 13), 251) . '.xml',
            $safename
        );
        $this->assertStringEndsWith('.xml', $safename);
        $this->assertTrue(mb_check_encoding($safename, 'UTF-8'));
    }

    /**
     * Test truncation that would otherwise split a two-byte UTF-8 character.
     */
    public function test_get_safe_import_filename_truncates_at_utf8_boundary(): void {
        $filename = str_repeat('a', 250) . 'Ж.xml';

        $this->assertSame(str_repeat('a', 250) . '.xml', helper::get_safe_import_filename($filename));
    }

    /**
     * Test multibyte filename exceeding 255 bytes without an extension.
     */
    public function test_get_safe_import_filename_multibyte_no_extension(): void {
        $filename = str_repeat('Тест', 80); // 320 bytes in UTF-8.
        $safename = helper::get_safe_import_filename($filename);

        $this->assertLessThanOrEqual(helper::MAX_TEMP_FILENAME_BYTES, strlen($safename));
        $this->assertTrue(mb_check_encoding($safename, 'UTF-8'));
    }

    /**
     * Test filename where the extension itself is abnormally long.
     */
    public function test_get_safe_import_filename_long_extension(): void {
        $filename = 'test.' . str_repeat('ext', 100);
        $safename = helper::get_safe_import_filename($filename);

        $this->assertSame(substr($filename, 0, helper::MAX_TEMP_FILENAME_BYTES), $safename);
        $this->assertLessThanOrEqual(helper::MAX_TEMP_FILENAME_BYTES, strlen($safename));
        $this->assertTrue(mb_check_encoding($safename, 'UTF-8'));
    }
}

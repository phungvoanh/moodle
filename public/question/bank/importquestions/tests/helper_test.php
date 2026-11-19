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
 * Tests for the question import helper.
 *
 * @package    qbank_importquestions
 * @copyright  2026 Phung Vo Anh <phung.voanh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(helper::class)]
final class helper_test extends \basic_testcase {
    /**
     * Test temporary filename generation.
     *
     * @param string $realfilename Original filename.
     * @param string $expected Expected temporary filename.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('temp_import_filename_provider')]
    public function test_get_temp_import_filename(string $realfilename, string $expected): void {
        $this->assertSame($expected, helper::get_temp_import_filename($realfilename));
    }

    /**
     * Data provider for {@see test_get_temp_import_filename}.
     *
     * @return array
     */
    public static function temp_import_filename_provider(): array {
        return [
            'simple xml' => ['questions.xml', 'import.xml'],
            'blackboard dat' => ['export.DAT', 'import.DAT'],
            'no extension' => ['questions', 'import'],
            'trailing dot' => ['questions.', 'import'],
            'multiple dots' => ['my.questions.gift', 'import.gift'],
            'path traversal in name' => ['questions.x/../y', 'import'],
            'unsafe extension chars' => ['questions.x-y!', 'import.xy'],
            'long extension is capped' => ['file.' . str_repeat('a', 50), 'import.' . str_repeat('a', 10)],
            'long cyrillic name' => [str_repeat('ВопросТеста', 13) . '.xml', 'import.xml'],
        ];
    }

    /**
     * Test the temporary filename never exceeds the filesystem limit.
     */
    public function test_get_temp_import_filename_length(): void {
        $name = helper::get_temp_import_filename(str_repeat('Ж', 500) . '.' . str_repeat('b', 500));
        $this->assertLessThanOrEqual(255, strlen($name));
    }
}

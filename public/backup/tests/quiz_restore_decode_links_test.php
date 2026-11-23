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

namespace core_backup;

defined('MOODLE_INTERNAL') || die();

// Include all the needed stuff.
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Decode links quiz restore tests.
 *
 * @package    core_backup
 * @copyright  2020 Ilya Tregubov <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_plugin::class)]
final class quiz_restore_decode_links_test extends \advanced_testcase {

    /**
     * Test restore_decode_rule class
     */
    public function test_restore_quiz_decode_links(): void {
        global $DB, $CFG, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            array('format' => 'topics', 'numsections' => 3,
                'enablecompletion' => COMPLETION_ENABLED),
            array('createsections' => true));
        $quiz = $generator->create_module('quiz', array(
            'course' => $course->id));
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);

        // Create questions.

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $context = \context_module::instance($qbank->cmid);
        $cat = $questiongenerator->create_question_category(array('contextid' => $context->id));
        $question = $questiongenerator->create_question('multichoice', null, array('category' => $cat->id));

        // Add to the quiz.
        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\external\submit_question_version::execute(
                $DB->get_field('quiz_slots', 'id', ['quizid' => $quiz->id, 'slot' => 1]), 1);

        $questiondata = \question_bank::load_question_data($question->id);

        $DB->set_field('question', 'questiontext', $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid, ['id' => $question->id]);

        $firstanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            ['id' => $firstanswer->id]);

        $secondanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid,
            ['id' => $secondanswer->id]);

        $thirdanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/grade/report/index.php?id=' . $quiz->cmid,
            ['id' => $thirdanswer->id]);

        $fourthanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/mod/quiz/index.php?id=' . $quiz->cmid,
            ['id' => $fourthanswer->id]);

        $newcm = \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);

        $quizquestions = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $newcm->instance, \context_module::instance($newcm->id));
        $questionids = [];
        foreach ($quizquestions as $quizquestion) {
            if ($quizquestion->questionid) {
                $this->assertEquals($CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid, $quizquestion->questiontext);
                $questionids[] = $quizquestion->questionid;
            }
        }
        list($condition, $param) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'questionid');
        $condition = 'WHERE qa.question ' . $condition;

        $sql = "SELECT qa.id,
                       qa.answer
                  FROM {question_answers} qa
                  $condition";
        $answers = $DB->get_records_sql($sql, $param);

        $this->assertEquals($CFG->wwwroot . '/course/view.php?id=' . $course->id, $answers[$firstanswer->id]->answer);
        $this->assertEquals($CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid, $answers[$secondanswer->id]->answer);
        $this->assertEquals($CFG->wwwroot . '/grade/report/index.php?id=' . $quiz->cmid, $answers[$thirdanswer->id]->answer);
        $this->assertEquals($CFG->wwwroot . '/mod/quiz/index.php?id=' . $quiz->cmid, $answers[$fourthanswer->id]->answer);
    }

    /**
     * Test duplicating a quiz that reuses a question whose answers contain links to non-quiz activities or H5P embeds.
     */
    public function test_restore_reused_question_with_non_quiz_activity_links(): void {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $scorm = $generator->create_module('scorm', ['course' => $course->id]);
        $page = $generator->create_module('page', ['course' => $course->id]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $context = \context_module::instance($qbank->cmid);
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $cat->id]);

        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\external\submit_question_version::execute(
            $DB->get_field('quiz_slots', 'id', ['quizid' => $quiz->id, 'slot' => 1]),
            1,
        );

        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id ASC'));
        $this->assertCount(4, $answers);

        $expectedanswers = [
            $answers[0]->id => '<p>Read <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id=' . $scorm->cmid
                . '">Scorm</a> before answering.</p>',
            $answers[1]->id => '<p>See <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id
                . '">Course</a> and <a href="' . $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid . '">Page</a>.</p>',
            $answers[2]->id => '<p><iframe src="' . $CFG->wwwroot . '/h5p/embed.php?url=https://example.com/test">'
                . '</iframe> and <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id=' . $scorm->cmid . '">Scorm</a></p>',
            $answers[3]->id => $CFG->wwwroot . '/h5p/embed.php?url=123',
        ];
        $this->assertGreaterThanOrEqual(32, strlen($expectedanswers[$answers[3]->id]));
        foreach ($expectedanswers as $answerid => $answertext) {
            $DB->set_field('question_answers', 'answer', $answertext, ['id' => $answerid]);
        }

        $initialquestioncount = $DB->count_records('question');

        $newcm = \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);

        // The existing question must be reused rather than duplicated.
        $this->assertEquals($initialquestioncount, $DB->count_records('question'));

        $quizquestions = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $newcm->instance,
            \context_module::instance($newcm->id),
        );
        $reusedquestionids = array_values(array_filter(array_map(fn($q) => $q->questionid, $quizquestions)));
        $this->assertEquals([$question->id], $reusedquestionids);

        $storedanswers = $DB->get_records_menu('question_answers', ['question' => $question->id], 'id ASC', 'id, answer');
        $this->assertEquals($expectedanswers, $storedanswers);
    }

    /**
     * Test backing up and restoring a quiz into an existing course when a reused question answer contains a non-quiz link.
     */
    public function test_restore_reused_question_backup_restore_into_existing_course(): void {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $scorm = $generator->create_module('scorm', ['course' => $course->id]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $context = \context_module::instance($qbank->cmid);
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $cat->id]);

        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\external\submit_question_version::execute(
            $DB->get_field('quiz_slots', 'id', ['quizid' => $quiz->id, 'slot' => 1]),
            1,
        );

        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id ASC'));
        $legacyfileurl = \moodle_url::make_legacyfile_url($course->id, 'pic.jpg')->out(true);
        $expectedanswers = [
            $answers[0]->id => '<p>Read <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id=' . $scorm->cmid
                . '">Scorm</a> before answering.</p>',
            $answers[1]->id => '<p><img src="' . $legacyfileurl . '" alt="Pic"> and <a href="'
                . $CFG->wwwroot . '/mod/scorm/view.php?id=' . $scorm->cmid . '">Scorm</a></p>',
        ];
        foreach ($expectedanswers as $answerid => $answertext) {
            $DB->set_field('question_answers', 'answer', $answertext, ['id' => $answerid]);
        }

        $initialquestioncount = $DB->count_records('question');

        // Back up the quiz activity.
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $quiz->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
        );
        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $bc->destroy();

        if (!file_exists($backupbasepath . '/moodle_backup.xml')) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backupbasepath);
        }

        // Reset the encoder cache to simulate a standalone restore request.
        $originalencodercache = $GLOBALS['LINKS_ENCODERS_CACHE'] ?? [];
        $GLOBALS['LINKS_ENCODERS_CACHE'] = [];

        try {
            $rc = new \restore_controller(
                $backupid,
                $course->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_ADDING,
            );
            $this->assertTrue($rc->execute_precheck());
            $rc->execute_plan();
            $rc->destroy();
        } finally {
            $GLOBALS['LINKS_ENCODERS_CACHE'] = $originalencodercache;
        }

        $this->assertEquals($initialquestioncount, $DB->count_records('question'));

        $modinfo = get_fast_modinfo($course->id);
        $quizzes = $modinfo->get_instances_of('quiz');
        $this->assertCount(2, $quizzes);
        $restoredcm = null;
        foreach ($quizzes as $cm) {
            if ((int) $cm->id !== (int) $quiz->cmid) {
                $restoredcm = $cm;
                break;
            }
        }
        $this->assertNotNull($restoredcm);

        $quizquestions = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $restoredcm->instance,
            \context_module::instance($restoredcm->id),
        );
        $reusedquestionids = array_values(array_filter(array_map(fn($q) => $q->questionid, $quizquestions)));
        $this->assertEquals([$question->id], $reusedquestionids);
        foreach ($expectedanswers as $answerid => $answertext) {
            $this->assertEquals($answertext, $DB->get_field('question_answers', 'answer', ['id' => $answerid]));
        }
    }

    /**
     * Test duplicating a quiz when a reused answer has a legacy file.php URL that only matches via re-encoding.
     */
    public function test_restore_reused_question_with_reencoded_legacy_file_url(): void {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Ensure process_cdata() decodes $@FILEPHP@$ into /file.php/{courseid}/... while the DB holds ?file=...
        $CFG->slasharguments = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $scorm = $generator->create_module('scorm', ['course' => $course->id]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $context = \context_module::instance($qbank->cmid);
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $cat->id]);

        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\external\submit_question_version::execute(
            $DB->get_field('quiz_slots', 'id', ['quizid' => $quiz->id, 'slot' => 1]),
            1,
        );

        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id ASC'));
        $expectedanswers = [
            $answers[0]->id => '<p><img src="' . $CFG->wwwroot . '/file.php?file=/' . $course->id
                . '/pic.jpg" alt="Pic"></p>',
            $answers[1]->id => '<p><img src="' . $CFG->wwwroot . '/file.php?file=%2F' . $course->id
                . '%2Fpic.jpg" alt="Pic"> and <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id='
                . $scorm->cmid . '">Scorm</a></p>',
        ];
        foreach ($expectedanswers as $answerid => $answertext) {
            $DB->set_field('question_answers', 'answer', $answertext, ['id' => $answerid]);
        }

        $initialquestioncount = $DB->count_records('question');

        $newcm = \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);

        // The existing question must be reused rather than duplicated.
        $this->assertEquals($initialquestioncount, $DB->count_records('question'));

        $quizquestions = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $newcm->instance,
            \context_module::instance($newcm->id),
        );
        $reusedquestionids = array_values(array_filter(array_map(fn($q) => $q->questionid, $quizquestions)));
        $this->assertEquals([$question->id], $reusedquestionids);

        foreach ($expectedanswers as $answerid => $answertext) {
            $this->assertEquals($answertext, $DB->get_field('question_answers', 'answer', ['id' => $answerid]));
        }
    }

    /**
     * Test that process_question_answer uses its own decoder and does not mutate the restore plan decoder.
     */
    public function test_process_question_answer_does_not_pollute_plan_decoder(): void {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $context = \context_module::instance($qbank->cmid);
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('multichoice', null, ['category' => $cat->id]);

        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id ASC'));
        $plainanswer = '<p>A plain answer longer than thirty-two characters without links.</p>';
        $localsiteanswer = '<p>See <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id=55">Local Scorm</a> activity.</p>';
        $remotesiteanswer = '<p>See <a href="https://othersite.example.com/mod/scorm/view.php?id=99">'
            . 'Remote Scorm</a> activity.</p>';
        $mixedremappedanswer = '<p><iframe src="' . $CFG->wwwroot . '/h5p/embed.php?url=https://example.com/test">'
            . '</iframe> and <a href="' . $CFG->wwwroot . '/mod/scorm/view.php?id=77">Mapped Scorm</a></p>';
        $DB->set_field('question_answers', 'answer', $plainanswer, ['id' => $answers[0]->id]);
        $DB->set_field('question_answers', 'answer', $localsiteanswer, ['id' => $answers[1]->id]);
        $DB->set_field('question_answers', 'answer', $remotesiteanswer, ['id' => $answers[2]->id]);
        $DB->set_field('question_answers', 'answer', $mixedremappedanswer, ['id' => $answers[3]->id]);

        $restoreid = 'testrestoreid';
        \restore_controller_dbops::create_restore_temp_tables($restoreid);

        try {
            \restore_dbops::set_backup_ids_record($restoreid, 'course_module', 77, 888);

            $task = $this->createMock(\restore_task::class);
            $task->expects($this->never())->method('get_decoder');
            $task->expects($this->once())->method('get_courseid')->willReturn($course->id);
            $task->expects($this->once())->method('get_restoreid')->willReturn($restoreid);
            $task->expects($this->once())->method('get_info')->willReturn(
                (object) ['original_wwwroot' => 'https://othersite.example.com'],
            );

            $mappedanswers = [];
            $step = $this->createMock(\restore_structure_step::class);
            $step->method('get_task')->willReturn($task);
            $step->method('get_old_parentid')->willReturn($question->id);
            $step->method('get_new_parentid')->willReturn($question->id);
            $step->method('get_mappingid')->willReturn(false);
            $step->method('set_mapping')->willReturnCallback(
                function ($itemname, $oldid, $newid) use (&$mappedanswers): void {
                    if ($itemname === 'question_answer') {
                        $mappedanswers[$oldid] = $newid;
                    }
                },
            );

            $plugin = new class ('qtype', 'multichoice', $step) extends \restore_qtype_plugin {
            };

            // Plain text answer matches directly without initializing transformer or decoder.
            $plugin->process_question_answer((object) [
                'id' => 1000,
                'answertext' => $plainanswer,
                'fraction' => '1.0',
            ]);

            // Same-site SCORM placeholder lazily initializes transformer and skips fallback decoder.
            $plugin->process_question_answer((object) [
                'id' => 1001,
                'answertext' => '<p>See <a href="$@SCORMVIEWBYID*55@$">Local Scorm</a> activity.</p>',
                'fraction' => '0.0',
            ]);

            // Mixed parser-decoded H5P link and remapped SCORM placeholder matches via re-encode only.
            $plugin->process_question_answer((object) [
                'id' => 1004,
                'answertext' => '<p><iframe src="' . $CFG->wwwroot . '/h5p/embed.php?url=https://example.com/test">'
                    . '</iframe> and <a href="$@SCORMVIEWBYID*77@$">Mapped Scorm</a></p>',
                'fraction' => '0.0',
            ]);

            // Cross-site SCORM placeholder matched via dedicated fallback decoder.
            $plugin->process_question_answer((object) [
                'id' => 1002,
                'answertext' => '<p>See <a href="$@SCORMVIEWBYID*99@$">Remote Scorm</a> activity.</p>',
                'fraction' => '0.0',
            ]);

            $this->assertEquals([
                1000 => $answers[0]->id,
                1001 => $answers[1]->id,
                1004 => $answers[3]->id,
                1002 => $answers[2]->id,
            ], $mappedanswers);

            // Unmatched answer text must still throw error_question_answers_missing_in_db.
            try {
                $plugin->process_question_answer((object) [
                    'id' => 1003,
                    'answertext' => '<p>Non-existent answer</p>',
                    'fraction' => '0.0',
                ]);
                $this->fail('Expected restore_step_exception was not thrown.');
            } catch (\restore_step_exception $e) {
                $this->assertEquals('error_question_answers_missing_in_db', $e->errorcode);
            }
        } finally {
            \restore_controller_dbops::drop_restore_temp_tables($restoreid);
        }
    }
}

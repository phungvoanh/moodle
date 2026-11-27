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

declare(strict_types=1);

namespace core_admin;

/**
 * Unit tests for core_admin_renderer::warn_if_not_registered().
 *
 * @package     core_admin
 * @copyright   2026 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\core_admin_renderer::class)]
final class renderer_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * Clear the static registration cache so each test sees the current database state.
     *
     * \core\hub\registration caches the registration record in a static property that is not
     * reset between tests, so without this a record from an earlier test leaks into this one.
     */
    protected function setUp(): void {
        parent::setUp();

        $property = new \ReflectionProperty(\core\hub\registration::class, 'registration');
        $property->setValue(null, null);
    }

    /**
     * Get a core_admin_renderer instance.
     *
     * @return \core_admin_renderer
     */
    protected function get_renderer(): \core_admin_renderer {
        global $PAGE;
        return $PAGE->get_renderer('core', 'admin');
    }

    /**
     * Register the site locally so \core\hub\registration::is_registered() returns true.
     */
    private function register_site(): void {
        global $CFG, $DB;

        // Paused-reporting warnings only apply to publicly accessible sites. Pin the value rather than
        // letting site_is_public() resolve the test wwwroot, so these tests do not depend on host resolution.
        $CFG->site_is_public = true;
        $DB->insert_record('registration_hubs', [
            'token' => 'abc123',
            'hubname' => 'Moodle.org',
            'huburl' => HUB_MOODLEORGHUBURL,
            'confirmed' => 1,
            'secret' => 'secret123',
            'timemodified' => time(),
        ]);
        set_config('site_regupdateversion', max(array_keys(\core\hub\registration::CONFIRM_NEW_FIELDS)), 'hub');
    }

    /**
     * A registered site that is reporting normally shows no registration warning.
     */
    public function test_warn_if_not_registered_reporting_normally(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->register_site();

        $this->assertSame('', $this->get_renderer()->warn_if_not_registered());
    }

    /**
     * A registered site with unconfirmed new registration fields shows no warning here.
     *
     * Every page rendering this warning for a registered site also calls
     * \core\hub\registration::registration_reminder(), which redirects the admin to the registration form
     * for that cause before this warning could render.
     */
    public function test_warn_if_not_registered_new_fields_pending(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->register_site();
        // Reset to before the first CONFIRM_NEW_FIELDS entry, so every new field is pending confirmation.
        set_config('site_regupdateversion', 0, 'hub');

        $this->assertSame(
            \core\hub\registration::REPORTING_PAUSED_NEW_FIELDS,
            \core\hub\registration::get_reporting_paused_reason(),
        );
        $this->assertSame('', $this->get_renderer()->warn_if_not_registered());
    }

    /**
     * A registered site with the registration cron task disabled shows a persistent warning.
     */
    public function test_warn_if_not_registered_task_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->register_site();

        $task = \core\task\manager::get_scheduled_task(\core\task\registration_cron_task::class);
        $task->set_disabled(true);
        \core\task\manager::configure_scheduled_task($task);

        $warning = $this->get_renderer()->warn_if_not_registered();
        $this->assertStringContainsString(get_string('registrationreportingpausedtaskdisabled', 'admin'), $warning);
        $this->assertStringContainsString('alert-warning', $warning);
        $this->assertStringNotContainsString('alert-danger', $warning);
    }

    /**
     * A site that is not publicly accessible is not warned about paused reporting.
     */
    public function test_warn_if_not_registered_task_disabled_not_public(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->register_site();
        $CFG->site_is_public = false;

        $task = \core\task\manager::get_scheduled_task(\core\task\registration_cron_task::class);
        $task->set_disabled(true);
        \core\task\manager::configure_scheduled_task($task);

        $this->assertSame('', $this->get_renderer()->warn_if_not_registered());
    }

    /**
     * An unregistered public site still shows the original registration warning.
     */
    public function test_warn_if_not_registered_unregistered(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->site_is_public = true;

        $warning = $this->get_renderer()->warn_if_not_registered();
        $this->assertStringContainsString(get_string('registrationwarning', 'admin'), $warning);
    }

    /**
     * A user without moodle/site:config (for example a Manager, who has moodle/site:configview and can
     * reach /admin/search.php) must not see the task-disabled paused-reporting warning: only admins who
     * can act on it should see it.
     */
    public function test_warn_if_not_registered_task_disabled_no_capability(): void {
        global $DB;

        $this->resetAfterTest();
        $this->register_site();

        $manageruser = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        role_assign($managerrole->id, $manageruser->id, \context_system::instance()->id);
        $this->setUser($manageruser);

        $task = \core\task\manager::get_scheduled_task(\core\task\registration_cron_task::class);
        $task->set_disabled(true);
        \core\task\manager::configure_scheduled_task($task);

        $this->assertSame('', $this->get_renderer()->warn_if_not_registered());
    }

    /**
     * Skipped future custom checks should appear in the Other checks table with an info badge and escaped info.
     */
    public function test_environment_check_table_displays_skipped_future_checks(): void {
        global $CFG;
        require_once($CFG->libdir . '/environmentlib.php');

        $this->resetAfterTest();

        $phpresult = new \environment_results('php');
        $phpresult->setStatus(true);
        $phpresult->setLevel('required');
        $phpresult->setCurrentVersion('8.3.0');
        $phpresult->setNeededVersion('8.3.0');

        $warningresult = new \environment_results('custom_check');
        $warningresult->setStatus(false);
        $warningresult->setLevel('optional');
        $warningresult->setInfo('optional_warning_check');

        $skippedoptional = new \environment_results('custom_check');
        $skippedoptional->setStatus(false);
        $skippedoptional->setLevel('optional');
        $skippedoptional->setInfo('\core\environment::check_composer_dependencies_installed');
        $skippedoptional->set_skipped(true);
        $skippedoptional->setFeedbackStr(['environmentfuturecustomcheckskipped', 'admin', '5.3']);

        $skippedrequired = new \environment_results('custom_check');
        $skippedrequired->setStatus(false);
        $skippedrequired->setLevel('required');
        $skippedrequired->setInfo('<script>future_check</script>');
        $skippedrequired->set_skipped(true);
        $skippedrequired->setFeedbackStr(['environmentfuturecustomcheckskipped', 'admin', '5.3']);

        $results = [$phpresult, $warningresult, $skippedoptional, $skippedrequired];
        $html = $this->get_renderer()->environment_check_table(true, $results);

        $this->assertStringContainsString('id="otherserverstatus"', $html);
        $this->assertStringContainsString('\core\environment::check_composer_dependencies_installed', $html);
        $this->assertStringContainsString('&lt;script&gt;future_check&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>future_check</script>', $html);
        $this->assertStringContainsString(get_string('environmentskippedcustomcheck', 'admin'), $html);
        $this->assertStringContainsString(get_string('environmentfuturecustomcheckskipped', 'admin', '5.3'), $html);
        $this->assertStringContainsString(get_string('skipped'), $html);
        $this->assertStringContainsString('badge text-bg-info', $html);
        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringNotContainsString(get_string('environmenterrortodo', 'admin'), $html);
        $this->assertStringNotContainsString(
            'admin/environment/custom_check/\core\environment::check_composer_dependencies_installed',
            $html
        );

        $warningpos = strpos($html, 'optional_warning_check');
        $skippedpos = strpos($html, '\core\environment::check_composer_dependencies_installed');
        $this->assertNotFalse($warningpos);
        $this->assertNotFalse($skippedpos);
        $this->assertLessThan($skippedpos, $warningpos);
    }
}

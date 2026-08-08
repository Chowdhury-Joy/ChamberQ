<?php

namespace App\Console\Commands;

use App\Support\ProductionReadiness;
use Illuminate\Console\Command;

/**
 * Refuse to call a deployment ready when it plainly is not.
 *
 * Run it as the last step of a deploy: a non-zero exit stops the release
 * rather than leaving a clinic serving stack traces or silently swallowing
 * every patient's confirmation SMS.
 */
class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check
                            {--strict : Treat warnings as failures too}';

    protected $description = 'Check this environment is configured for real patients before going live';

    public function handle(): int
    {
        $problems = ProductionReadiness::problems();

        if ($problems === []) {
            $this->info('Ready. No production configuration problems found.');

            return self::SUCCESS;
        }

        $blockers = array_values(array_filter(
            $problems,
            fn (array $p) => $p['severity'] === ProductionReadiness::SEVERITY_BLOCKER,
        ));
        $warnings = array_values(array_filter(
            $problems,
            fn (array $p) => $p['severity'] === ProductionReadiness::SEVERITY_WARNING,
        ));

        foreach ([['BLOCKER', $blockers], ['WARNING', $warnings]] as [$label, $set]) {
            foreach ($set as $problem) {
                $this->newLine();
                $this->line("  <fg=".($label === 'BLOCKER' ? 'red' : 'yellow').";options=bold>{$label}</>  {$problem['key']}");
                $this->line("           {$problem['problem']}");
                $this->line("           <fg=gray>Fix: {$problem['fix']}</>");
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '  %d blocker(s), %d warning(s).',
            count($blockers),
            count($warnings),
        ));

        if ($blockers !== []) {
            $this->newLine();
            $this->error('Not safe to serve patients. Fix the blockers above.');

            return self::FAILURE;
        }

        if ($warnings !== [] && $this->option('strict')) {
            $this->newLine();
            $this->error('Warnings present and --strict was passed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('No blockers, but the warnings above are unresolved risks.');

        return self::SUCCESS;
    }
}

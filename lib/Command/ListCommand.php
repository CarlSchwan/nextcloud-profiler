<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Profiler\Command;

use OC\Core\Command\Base;
use OC\DB\DbDataCollector;
use OCA\Profiler\DataCollector\MemoryDataCollector;
use OCP\Profiler\IProfiler;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	private IProfiler $profiler;

	public function __construct(IProfiler $profiler) {
		parent::__construct();
		$this->profiler = $profiler;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('profiler:list')
			->setDescription('List captured profiles')
			->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of profiles to return')
			->addOption('url', null, InputOption::VALUE_REQUIRED, 'Url to list profiles for')
			->addOption('since', null, InputOption::VALUE_REQUIRED, 'Minimum date for listed profiles, as unix timestamp')
		->addOption('before', null, InputOption::VALUE_REQUIRED, 'Maximum date for listed profiles, as unix timestamp');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$since = $input->getOption('since') ? (int)$input->getOption('since') : null;
		$before = $input->getOption('before') ? (int)$input->getOption('before') : null;
		$limit = $input->getOption('limit') ? (int)$input->getOption('limit') : 1000;
		$url = $input->getOption('url');

		$profiles = $this->profiler->find($url, $limit, null, $since, $before);
		$profiles = array_reverse($profiles);
		foreach ($profiles as &$profile) {
			$info = $this->profiler->loadProfile($profile['token']);

			/** @var DbDataCollector $dbCollector */
			$dbCollector = $info->getCollector('db');
			/** @var MemoryDataCollector $memoryCollector */
			$memoryCollector = $info->getCollector('memory');

			if ($dbCollector) {
				$profile['queries'] = count($dbCollector->getQueries());
			} else {
				$profile['queries'] = '--';
			}
			if ($memoryCollector) {
				$profile['memory'] = $memoryCollector->getMemory();
			} else {
				$profile['memory'] = '--';
			}
		}

		$outputType = $input->getOption('output');

		if ($profiles) {
			if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
				$this->writeArrayInOutputFormat($input, $output, $profiles);
			} else {
				$table = new Table($output);
				$table->setHeaders(array_keys($profiles[0]));
				$table->setRows($profiles);
				$table->render();
			}
		}

		return 0;
	}
}
<?php

namespace hashworks\Phergie\Plugin\xREL;

use Phergie\Irc\Bot\React\AbstractPlugin;
use \WyriHaximus\Phergie\Plugin\Http\Request;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Event\UserEvent;
use Phergie\Irc\Plugin\React\Command\CommandEvent;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package hashworks\Phergie\Plugin\xREL
 */
class Plugin extends AbstractPlugin {

	private $parseAllMessages = false;
	private $limit = 5;

	public function __construct ($config = array()) {
		if (isset($config['parseAllMessages'])) $this->parseAllMessages = boolval($config['parseAllMessages']);
		if (isset($config['limit'])) $this->limit = intval($config['limit']);
	}

	/**
	 * @return array
	 */
	public function getSubscribedEvents () {
		$events = array(
				'command.upcoming'      => 'handleUpcomingCommand',
				'command.latest'        => 'handleLatestCommand',
				'command.hot'           => 'handleHotCommand',
				'command.nfo'           => 'handleNfoCommand',
				'command.upcoming.help' => 'handleUpcomingHelp',
				'command.latest.help'   => 'handleLatestHelp',
				'command.hot.help'      => 'handleHotHelp',
				'command.nfo.help'      => 'handleNfoHelp',
		);

		if ($this->parseAllMessages) {
			$events['irc.received.privmsg'] = 'handleMessage';
			$events['irc.received.notice'] = 'handleMessage';
		}

		return $events;
	}

	/**
	 * Sends reply messages.
	 *
	 * @param UserEventInterface $event
	 * @param Queue              $queue
	 * @param array|string       $messages
	 */
	protected function sendReply (UserEventInterface $event, Queue $queue, $messages) {
		$method = 'irc' . $event->getCommand();
		if (is_array($messages)) {
			$target = $event->getSource();
			foreach ($messages as $message) {
				$queue->$method($target, $message);
			}
		} else {
			$queue->$method($event->getSource(), $messages);
		}
	}

	private function stringifyReleaseData ($data, $size = true, $url = true) {
		$string = array();
		if (isset($data['dirname'])) {
			$string[] = $data['dirname'];
		} else {
			return false;
		}
		if ($size && isset($data['size']['number'])) {
			$string[] = "[" . $data['size']['number'] . $data['size']['unit'] . "]";
		}
		if ($url && isset($data['link_href'])) {
			$string[] = "- " . $data['link_href'];
		}

		return join(' ', $string);
	}

	public function handleUpcomingHelp (CommandEvent $event, Queue $queue) {
		$this->sendReply($event, $queue, array(
				'Usage: upcoming',
				'Responds with a list of upcoming movies.'
		));
	}

	public function handleLatestHelp (CommandEvent $event, Queue $queue) {
		$this->sendReply($event, $queue, array(
				'Usage: latest [[hd-]movie|[hd-]tv|game|update|console|[hd-]xxx]',
				'Responds with a list of latest releases.'
		));
	}

	public function handleHotHelp (CommandEvent $event, Queue $queue) {
		$this->sendReply($event, $queue, array(
				'Usage: hot [movie|tv|game|console]',
				'Responds with a list of latest hot releases.'
		));
	}

	public function handleNfoHelp (CommandEvent $event, Queue $queue) {
		$this->sendReply($event, $queue, array(
				'Usage: nfo <dirname>',
				'Responds with a nfo link for the provided scene dirname.'
		));
	}

	public function handleUpcomingCommand (CommandEvent $event, Queue $queue) {
		$errorCallback = function ($error) use ($event, $queue) {
			$this->sendReply($event, $queue, $error);
		};

		$this->emitter->emit('http.request', [new Request([
				'url'             => 'http://api.xrel.to/api/calendar/upcoming.json',
				'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
					// remove /*-secure- ... */ encapsulation
					$data = trim(substr($data, 10, count($data) - 3));
					if (!empty($data) && ($data = json_decode($data, true)) !== null) {
						if (isset($data["payload"])) {
							$data = array_slice($data["payload"], 0, $this->limit);
							$messages = array();
							foreach ($data as $upcoming) {
								if (isset($upcoming['title'])) {
									if (isset($upcoming['genre']) && !empty($upcoming['genre'])) {
										$messages[] = $upcoming['title'] . " [" . $upcoming['genre'] . "] " . "[" . $upcoming['link_href'] . "]";
									} else {
										$messages[] = $upcoming['title'] . $upcoming['link_href'] . "]";
									}
								}
							}
							if (count($messages) > 0) {
								$this->sendReply($event, $queue, $messages);

								return;
							}
						}
					}
					$errorCallback('Failed to fetch upcoming stuff from xREL.to...');
				},
				'rejectCallback'  => $errorCallback
		])]);
	}

	public function handleLatestCommand (CommandEvent $event, Queue $queue) {
		$type = '';
		if (isset($event->getCustomParams()[0])) {
			$type = $event->getCustomParams()[0];
		}
		if (empty($type)) {
			$url = 'http://api.xrel.to/api/release/latest.json?filter=6&per_page=' . rawurlencode($this->limit);
		} elseif ($type == 'movie' || $type == 'tv' || $type == 'game' || $type == 'update' || $type == 'console' || $type == 'xxx' || $type == 'hd-movie' || $type == 'hd-tv' || $type == 'hd-xxx') {
			switch ($type) {
				case 'movie':
					$category_name = 'movies';
					break;
				case 'game':
					$category_name = 'games';
					break;
				case 'update':
					$type = 'game';
					$category_name = 'update';
					break;
				case 'hd-movie':
					$type = 'movie';
					$category_name = 'hdtv';
					break;
				case 'hd-tv':
					$type = 'tv';
					$category_name = 'hdtv';
					break;
				case 'hd-xxx':
					$type = 'xxx';
					$category_name = 'hdtv';
					break;
				default:
					$category_name = $type;
			}
			$url = 'http://api.xrel.to/api/release/browse_category.json?category_name=' . rawurlencode($category_name) .
					'&ext_info_type=' . rawurlencode($type);
		} else {
			$this->handleLatestHelp($event, $queue);

			return;
		}

		$errorCallback = function ($error) use ($event, $queue) {
			$this->sendReply($event, $queue, $error);
		};

		$this->emitter->emit('http.request', [new Request([
				'url'             => $url,
				'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
					// remove /*-secure- ... */ encapsulation
					$data = trim(substr($data, 10, count($data) - 3));
					if (!empty($data) && ($data = json_decode($data, true)) !== null) {
						if (isset($data['payload']['list'])) {
							$data = $data['payload']['list'];
							$messages = array();
							$data = array_slice($data, 0, $this->limit);
							foreach ($data as $release) {
								$string = $this->stringifyReleaseData($release, true, true);
								if ($string !== false) {
									$messages[] = $string;
								}
							}
							if (count($messages) > 0) {
								$this->sendReply($event, $queue, $messages);

								return;
							}
						}
					}
					$errorCallback('Failed to fetch latest releases from xREL.to...');
				},
				'rejectCallback'  => $errorCallback
		])]);
	}

	public function handleHotCommand (CommandEvent $event, Queue $queue) {
		$type = '';
		if (isset($event->getCustomParams()[0])) {
			$type = $event->getCustomParams()[0];
		}
		if (empty($type)) {
			$url = 'http://api.xrel.to/api/release/browse_category.json?category_name=hotstuff';
		} elseif ($type == 'movie') {
			$url = 'http://api.xrel.to/api/release/browse_category.json?category_name=topmovie&ext_info_type=movie';
		} elseif ($type == 'tv' || $type == 'game' || $type == 'console') {
			$url = 'http://api.xrel.to/api/release/browse_category.json?category_name=hotstuff&ext_info_type=' . rawurlencode($type);
		} else {
			$this->handleHotHelp($event, $queue);

			return;
		}

		$errorCallback = function ($error) use ($event, $queue) {
			$this->sendReply($event, $queue, $error);
		};

		$this->emitter->emit('http.request', [new Request([
				'url'             => $url,
				'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
					// remove /*-secure- ... */ encapsulation
					$data = trim(substr($data, 10, count($data) - 3));
					if (!empty($data) && ($data = json_decode($data, true)) !== null) {
						if (isset($data['payload']['list'])) {
							$data = $data['payload']['list'];
							$messages = array();
							$data = array_slice($data, 0, $this->limit);
							foreach ($data as $release) {
								$string = $this->stringifyReleaseData($release, true, true);
								if ($string !== false) {
									$messages[] = $string;
								}
							}
							if (count($messages) > 0) {
								$this->sendReply($event, $queue, $messages);

								return;
							}
						}
					}
				},
				'rejectCallback'  => $errorCallback
		])]);
	}

	public function handleNfoCommand (CommandEvent $event, Queue $queue, $showError = true) {
		if (isset($event->getCustomParams()[0])) {
			$errorCallback = function ($error) use ($event, $queue, $showError) {
				if ($showError) {
					$this->sendReply($event, $queue, $error);
				}
			};

			$this->emitter->emit('http.request', [new Request([
					'url'             => 'http://api.xrel.to/api/release/info.json?dirname=' . rawurlencode($event->getCustomParams()[0]),
					'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
						// remove /*-secure- ... */ encapsulation
						$data = trim(substr($data, 10, count($data) - 3));
						if (!empty($data) && ($data = json_decode($data, true)) !== null) {
							if (isset($data['payload'])) {
								$string = $this->stringifyReleaseData($data['payload'], true, true);
								if ($string !== false) {
									$this->sendReply($event, $queue, $string);
								}
							}
						} else {
							$errorCallback('Nothing found!');
						}
					},
					'rejectCallback'  => $errorCallback
			])]);
		} elseif ($showError) {
			$this->handleNfoHelp($event, $queue);
		}
	}

	public function handleMessage (UserEvent $event, Queue $queue) {
			// make sure we don't react twice when the nfo command gets triggered
		if (strpos($event->getMessage(), 'nfo ') === false) {
			if (preg_match_all("/[a-z0-9._]{4,}-[a-z0-9]{3,}/i", $event->getMessage(), $matches)) {
				$matches = array_slice($matches[0], 0, $this->limit);
				foreach ($matches as $dirname) {
					$commandEvent = new CommandEvent();
					$commandEvent->fromEvent($event);
					$commandEvent->setCustomParams(array($dirname));
					$this->handleNfoCommand($commandEvent, $queue, false);
				}
			}
		}
	}

}

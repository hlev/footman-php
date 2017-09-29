<?php

namespace Hlev\Footman;

use Exception;

class Footman {

	/** @var string $host */
	private $host;
	/** @var int $servicePort */
	private $servicePort;

	/**
	 * Footman constructor.
	 * @param string $host
	 * @param int $servicePort
	 */
	public function __construct($host, $servicePort) {
		$this->host        = $host;
		$this->servicePort = $servicePort;
	}

	/**
	 * @param string $command The command
	 * @param int $rcvTimeout (optional) Response timeout in seconds, default: unlimited
	 * @return string The response
	 * @throws Exception
	 */
	public function send($command, $rcvTimeout = null) {
		$socket   = $this->createSocket($rcvTimeout);
		$conn     = socket_connect($socket, $this->host, $this->servicePort);
		$length   = strlen($command);
		$sent     = 0;
		$response = '';

		if (!$conn) {
			throw new Exception(sprintf('Cannot connect to %s:%d: %s',
				$this->host,
				$this->servicePort,
				$this->getLastErrorStr($socket)
			));
		}

		while (true) {
			if (($retVal = socket_write($socket, $command)) === false) {
				throw new Exception(sprintf('Error while writing to socket: %s', $this->getLastErrorStr($socket)));
			}

			if (($sent += $retVal) < $length) {
				$command = substr($command, $retVal);
			} else {
				break;
			}
		}

		while (($retVal = socket_recv($socket, $data, 1024 * 8, MSG_WAITALL)) !== false) {

			if (is_null($data)) {
				break;
			}

			$response .= $data;
		}

		if ($retVal === false) {
			throw new Exception(sprintf('Error while receiving response: %s', $this->getLastErrorStr($socket)));
		}

		socket_close($socket);

		return $response;
	}

	/**
	 * @param int $rcvTimeout
	 * @return resource
	 * @throws Exception
	 */
	private function createSocket($rcvTimeout = null) {
		$socket  = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$rcvTimeout = [
			'sec'  => is_null($rcvTimeout) ? 0 : (int)$rcvTimeout,
			'usec' => 0
		];

		if (!$socket) {
			throw new Exception(sprintf('Unable to create socket: %s', $this->getLastErrorStr($socket)));
		}

		socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $rcvTimeout);

		if (!socket_bind($socket, $this->host)) {
			throw new Exception(sprintf('Unable to bind socket: %s', $this->getLastErrorStr($socket)));
		}

		return $socket;
	}


	/**
	 * @param resource $s
	 * @return string
	 */
	private function getLastErrorStr($s) {
		$err = socket_strerror(socket_last_error($s));
		socket_clear_error($s);

		return $err;
	}
}
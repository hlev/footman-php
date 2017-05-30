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

	public function send($command) {
		$socket   = $this->createSocket();
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

	private function createSocket() {
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if (!$socket) {
			throw new Exception(sprintf('Unable to create socket: %s', $this->getLastErrorStr($socket)));
		}

		if (!socket_bind($socket, $this->host)) {
			throw new Exception(sprintf('Unable to bind socket: %s', $this->getLastErrorStr($socket)));
		}

		return $socket;
	}


	private function getLastErrorStr($s) {
		$err = socket_strerror(socket_last_error($s));
		socket_clear_error($s);

		return $err;
	}
}
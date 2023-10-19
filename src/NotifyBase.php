<?php
/*  
    This is a Fork of aethalides/systemd-notify package
    
    Original copyright follows

    --------------------------------------------------------------

    Copyright(c) 2017 Aethalides@AndyPieters.me.uk

    This file is part of the aethalides/systemd-notify package

    aethalides/systemd-notify is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    aethalides/systemd-notify is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with aethalides/systemd-notify.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Trakosoft\Systemd\Notify;

/**
 *  Communicates with Systemd via a socket
 * 
 *  @package Trakosoft\Systemd\Notify
 */
abstract class NotifyBase implements SystemdNotifierInterface
{

    protected $arrVariables = [];

    protected $strSocketLocation = null;

    private $resSocket = null;

    public function setVariable(string $strVariable, $mxdValue = null): bool
    {

        if (!(0 === strpos($strVariable, 'X_') || in_array($strVariable, SystemdNotifierInterface::NOTIFY_VARIABLES))) {
            return false;
        }

        if (is_null($mxdValue)) {
            unset($this->arrVariables[$strVariable]);
            return true;
        }

        if (is_scalar($mxdValue)) {
            $this->arrVariables[$strVariable] = (string) $mxdValue;
            return true;
        }
        return false;
    }

    protected function setSocketLocation(?string $strSocket)
    {
        // Systemd passes notification socket via environment
        $strSocket = $strSocket ?? getenv('NOTIFY_SOCKET');
        if (strlen($strSocket) && file_exists($strSocket)) {
            $this->strSocketLocation = $strSocket;
        } else {
            throw NotifierError::socketNotFoundError($strSocket);
        }
    }

    private function formatForSending(array $arrVariables): ?string
    {
        return array_reduce(
            array_keys($arrVariables),
            function (?string $strCarry, string $strKey) use ($arrVariables): string {
                return sprintf(
                    '%s%s=%s%s',
                    $strCarry,
                    $strKey,
                    $arrVariables[$strKey],
                    PHP_EOL
                );
            }
        );
    }

    public function clearVariables()
    {
        $this->arrVariables = [];
    }

    public function getVariables(): array
    {
        return $this->arrVariables;
    }

    private function setVariables(array $arrVariables)
    {
        $this->arrVariables = $arrVariables;
    }

    public function open()
    {
        if (isset($this->resSocket)) {
            throw NotifierError::socketAlreadyOpenError();
        }
        $resSocket = socket_create(
            SystemdNotifierInterface::NOTIFY_SOCKET_DOMAIN,
            SystemdNotifierInterface::NOTIFY_SOCKET_TYPE,
            SystemdNotifierInterface::NOTIFY_SOCKET_PROTOCOL
        );
        if ($resSocket === false) {
            throw NotifierError::socketCreateError();
        }
        if (socket_connect($resSocket, $this->strSocketLocation)) {
            $this->resSocket = $resSocket;
        } else {
            throw NotifierError::socketConnectError($this->strSocketLocation, $resSocket);
        }
    }

    public function close()
    {
        if (!isset($this->resSocket)) {
            throw NotifierError::socketNotOpenError();
        }
        @socket_close($this->resSocket);
        $this->resSocket = null;
    }

    public function isOpened(): bool
    {
        return isset($this->resSocket);
    }

    public function isClosed(): bool
    {
        return !$this->isOpened();
    }

    public function openIfClosed()
    {
        if ($this->isClosed()) {
            $this->open();
        }
    }

    public function closeIfOpened()
    {
        if ($this->isOpened()) {
            $this->close();
        }
    }

    public function send()
    {
        if (!$this->arrVariables) {
            return;
        }
        if (!isset($this->resSocket)) {
            throw NotifierError::socketNotOpenError();
        }
        $strContents = $this->formatForSending($this->arrVariables);
        $intContentLength = strlen($strContents);
        $intWritten = socket_write($this->resSocket, $strContents, $intContentLength);
        if ($intContentLength <> $intWritten) {
            throw NotifierError::socketWriteError($intContentLength, $intWritten);
        }
    }
}

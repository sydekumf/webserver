<?php
/**
 * \TechDivision\WebServer\ServerContext
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Webserver
 * @package   TechDivision_WebServer
 * @author    Johann Zelger <jz@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/TechDivision_WebServer
 */

namespace TechDivision\WebServer;

use TechDivision\WebServer\ConnectionPool;
use TechDivision\WebServer\Exceptions\ConnectionHandlerNotFoundException;
use TechDivision\WebServer\Exceptions\ModuleNotFoundException;
use TechDivision\WebServer\Exceptions\ServerException;
use TechDivision\WebServer\Interfaces\ConfigInterface;
use TechDivision\WebServer\Interfaces\ServerConfigurationInterface;
use TechDivision\WebServer\Interfaces\ServerContextInterface;
use TechDivision\WebServer\Modules\CoreModule;
use TechDivision\WebServer\Modules\DirectoryModule;
use TechDivision\WebServer\Sockets\SocketInterface;
use TechDivision\WebServer\Interfaces\PoolInterface;
use TechDivision\WebServer\Dictionaries\ServerVars;

/**
 * Class ServerContext
 *
 * @category  Webserver
 * @package   TechDivision_WebServer
 * @author    Johann Zelger <jz@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/TechDivision_WebServer
 */
class ServerContext implements ServerContextInterface
{

    /**
     * Optionally hold's an container implementation of third party environment.
     * So every mod depending on his environment can use this as a container to transfer environment specific stuff.
     *
     * @var mixed
     */
    protected $container;

    /**
     * Hold's the config instance
     *
     * @var \TechDivision\WebServer\Interfaces\ServerConfigurationInterface
     */
    protected $serverConfig;

    /**
     * Initialises the server context
     *
     * @param \TechDivision\WebServer\Interfaces\ServerConfigurationInterface $serverConfig The servers configuration
     *
     * @return void
     */
    public function init(ServerConfigurationInterface $serverConfig)
    {
        // set configuration
        $this->serverConfig = $serverConfig;

        // init server vars
        $this->initServerVars();
    }

    /**
     * Return's the server config instance
     *
     * @return \TechDivision\WebServer\Interfaces\ServerConfigurationInterface The server config instance
     */
    public function getServerConfig()
    {
        return $this->serverConfig;
    }

    /**
     * Return's the server connection instance
     *
     * @param resource $connectionResource The socket resource
     *
     * @return SocketInterface The server connection instance
     */
    public function getConnectionInstance($connectionResource)
    {
        $socketType = $this->getServerConfig()->getSocketType();
        return $socketType::getInstance($connectionResource);
    }

    /**
     * Injects the container for further use in specific webserver mods etc...
     *
     * @param mixed $container An container instance for third party environment
     *
     * @return void
     */
    public function injectContainer($container)
    {
        $this->container = $container;
    }

    /**
     * Return's the container instance
     *
     * @return mixed The container instance for third party environment
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set's a value to specific server var
     *
     * @param string $serverVar The server var to set
     * @param string $value     The value to server var
     *
     * @return void
     */
    public function setServerVar($serverVar, $value)
    {
        if (!is_null($value)) {
            $this->serverVars[$serverVar] = $value;
        }
    }

    /**
     * Return's a value for specific server var
     *
     * @param string $serverVar The server var to get value for
     *
     * @return string The value to given server var
     */
    public function getServerVar($serverVar)
    {
        // check if server var is set
        if (isset($this->serverVars[$serverVar])) {
            // return server vars value
            return $this->serverVars[$serverVar];
        }
        // throw exception
        throw new ServerException("Server var '$serverVar'' does not exist.", 500);
    }

    /**
     * Return's all the server vars as array key value pair format
     *
     * @return array The server vars as array
     */
    public function getServerVars()
    {
        return $this->serverVars;
    }

    /**
     * Check's if value exists for given server var
     *
     * @param string $serverVar The server var to check
     *
     * @return bool Weather it has serverVar (true) or not (false)
     */
    public function hasServerVar($serverVar)
    {
        // check if server var is set
        if (!isset($this->serverVars[$serverVar])) {
            return false;
        }
        return true;
    }

    /**
     * Init basic server vars
     *
     * @return void
     */
    public function initServerVars()
    {
        // set server vars to local var to shorter usage
        $serverSoftware = $this->getServerConfig()->getSoftware() . ' (PHP ' . PHP_VERSION . ')';
        $serverAddress = $this->getServerConfig()->getAddress();
        $serverPort = $this->getServerConfig()->getPort();
        // set server vars array
        $this->serverVars = array(
            ServerVars::DOCUMENT_ROOT => $this->getServerConfig()->getDocumentRoot(),
            ServerVars::SERVER_ADMIN => $this->getServerConfig()->getAdmin(),
            ServerVars::SERVER_NAME => $serverAddress,
            ServerVars::SERVER_ADDR => $serverAddress,
            ServerVars::SERVER_PORT => $serverPort,
            ServerVars::GATEWAY_INTERFACE => "PHP/" . PHP_VERSION,
            ServerVars::SERVER_SOFTWARE => $serverSoftware,
            ServerVars::SERVER_SIGNATURE => "<address>$serverSoftware Server at $serverAddress Port $serverPort</address>\r\n",
            ServerVars::SERVER_HANDLER => CoreModule::MODULE_NAME,
            ServerVars::SERVER_ERRORS_PAGE_TEMPLATE_PATH => $this->getServerConfig()->getErrorsPageTemplatePath(),
            ServerVars::PATH => getenv('PATH'),
            ServerVars::HTTPS => ServerVars::VALUE_HTTPS_OFF
        );

        // check if ssl is going on and set server var for it like apache does
        if ($this->getServerConfig()->getTransport() === 'ssl') {
            $this->setServerVar(ServerVars::HTTPS, ServerVars::VALUE_HTTPS_ON);
        }
    }

    /**
     * Add's connection relevant informations to server vars
     *
     * @param \TechDivision\WebServer\Sockets\SocketInterface $connection The connection
     *
     * @return void
     */
    public function setConnectionServerVars(SocketInterface $connection)
    {
        /**
         * fill server vars with connection info
         * Not yet implemented due to performance issues
         *
         * REMOTE_HOST
         * REMOTE_IDENT
         */
        $this->setServerVar(ServerVars::REMOTE_ADDR, $connection->getAddress());
        $this->setServerVar(ServerVars::REMOTE_PORT, $connection->getPort());
    }
}

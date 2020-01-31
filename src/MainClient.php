<?php declare(strict_types=1);

namespace BrunoNatali\Connection;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\MultiFunction;

class MainClient implements MainClientInterface
{
    Public $loop;
    Public $multiFunction;
    Protected $outSystem;
    Private $clients = [
        'browser' => []
    ];

    Private $requests = [
        'timer' => [],
        'header' => [],
        'data' => [],
        'uri' => [],
        'error' => []
    ];
    Private static $browserConfig = [
        'timeout' => 10.0,
        'followRedirects' => false
    ];

    function __construct(array $config = [])
    {
        $config = OutSystem::helpHandleAppName( 
            $config,
            ["outSystemName" => "MainClient"]
        );
        $this->outSystem = new OutSystem($config);

        // Debug class initialization
        // $this->outSystem->stdout(BrunoNatali\Tools\Encapsulation::formatClassConfigs4InitializationPrint($config));

        $this->loop = Factory::create();
        $this->multiFunction = new MultiFunction($config);
    }

    Public function &getLoop()
    {
        return $this->loop;
    }


    /**
    * if (string)name is provided, client will be stored in array to be auto handled 
    * @ input name as string" or integer
    * @ input configs as array
    * @ output browser object as Clue\React\Buzz\Browser
    */
    Public function newBrowser(...$args)
    {
        $config = [];
        $name = null;
        foreach ($args as $value) {
            if ((is_string($value) && trim($value) != "") || is_int($value)) $name = $value;
            else if (is_array($value)) $config = $value;
        }

        if ($name !== null) {
            $thisBrowserName = null;
            $this->clients['browser'][$name] = &$thisBrowserName;
            $this->requests['timer'][$name] = null;
            $this->requests['header'][$name] = null;
            $this->requests['data'][$name] = null;
            $this->requests['error'][$name] = null;
            $this->multiFunction->new($name); 
        } 
        
        $thisBrowserName = new Clue\React\Buzz\Browser($this->loop);
        $thisBrowserName = $thisBrowserName->withOptions(array_merge(self::$browserConfig, $config));

        $this->outSystem->stdout("Created Browser $name.", OutSystem::LEVEL_ALL);
        return $thisBrowserName;
    }

    /**
    * 
    * @ input client as string" or Clue\React\Buzz\Browser object
    * @ input uri as string
    * @ input responseFunction as function - function called when GET return on error or sucess
    * @ output browser object as Clue\React\Buzz\Browser
    */
    Public function get($client, string $uri, callable $responseFunction = null)
    {
        if (is_object($client) && $client instanceof Clue\React\Buzz\Browser) {
            if (!is_callable($responseFunction)) throw new \Exception("Response function could not be null.");
            $clientBrowser = &$client;
            $clientindex = null;
        } else if (isset($this->clients['browser'][$client])) {
            $this->requests['uri'][$client] = $uri;
            $clientBrowser = &$this->clients['browser'][$client];
            $clientindex = $client;
        } else throw new \Exception("Browser client not exist.");
        
        $this->outSystem->stdout(
            "Request GET on $uri using " . ($clientindex === null ? "Browser object" : "index $client"), 
            OutSystem::LEVEL_ALL
        );

        $me = &$this;
        $clientBrowser->get($uri)->then(
            function (Psr\Http\Message\ResponseInterface $response) use ($me, $responseFunction, $clientindex) {
                if ($clientindex !== null) {
                    $me->requests['header'][$clientindex] = $response->getHeaders();
                    $me->requests['data'][$clientindex] = (string)$response->getBody();
                    $this->outSystem->stdout(
                        "Answer from '" . $me->requests['uri'][$clientindex] . "' with " . strlen($me->requests['data'][$clientindex]) . "bytes", 
                        OutSystem::LEVEL_ALL
                    );
                }
                if (is_callable($responseFunction))
                    $responseFunction(
                        self::SERVER_RESPONSE_STRING,
                        ($clientindex !== null ? $me->requests['data'][$clientindex] : (string)$response->getBody()),
                        ($clientindex !== null ? $me->requests['header'][$clientindex] : (string)$response->getHeaders())
                    );
            },
            function (\Exception $error) use ($me, $responseFunction, $clientindex) {
                if ($clientindex !== null) {
                    $me->requests['error'][$clientindex] = $error->getMessage();
                    $this->outSystem->stdout(
                        "Error requesting from '" . $me->requests['uri'][$clientindex] . "': " . $me->requests['error'][$clientindex], 
                        OutSystem::LEVEL_ALL
                    );
                }
                if (is_callable($responseFunction))
                    $responseFunction(
                        self::ERROR_HTTP_REQUEST,
                        ($clientindex !== null ? $me->requests['error'][$clientindex] : $error->getMessage())
                    );
                else 
                    var_dump('There was an error GET', $error->getMessage());
            }
        );
    }

    Public function setPeriodicRequest(int $type, float $time, $uri, $browser = null): bool
    {
        $me = &$this;
        if ((is_string($browser) || is_int($browser)) && isset($this->clients['browser'][$browser])) {
            $this->outSystem->stdout(
                "Scheduling Resquest $type to $time seconds using Browser $browser", 
                OutSystem::LEVEL_ALL
            );

            $this->requests['timer'][$browser] = $this->loop
            ->addTimer($time, function () use ($me, $type, $time, $uri, $browser) {
                $me->outSystem->stdout(
                    "Executing scheduled request for $browser", 
                    OutSystem::LEVEL_ALL
                );
                if ($type === self::REQUEST_TYPE_GET) {
                    $me->get($browser, $uri, function($responseType, $body, $header) use ($me, $type, $browser, $time, $uri){
                        //var_dump("Response", $type, $body);
                        $me->multiFunction->$browser->exec($responseType, $body);
                        $me->setPeriodicRequest($type, $time, $uri, $browser);
                    });
                } else if ($type === self::REQUEST_TYPE_POST) {

                }
            });

            return true;
        }
        
        return false;
    }
}
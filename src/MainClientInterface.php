<?php declare(strict_types=1);

namespace BrunoNatali\Connection;

interface MainClientInterface 
{
    Const ERROR_OK = 0x00;              // No error
    Const ERROR_HTTP_REQUEST = 0x21;    // GET / POST error
    Const SERVER_RESPONSE_STRING = 0x31;// Server response in string

    Const REQUEST_TYPE_GET = 0x81;      // GET request
    Const REQUEST_TYPE_POST = 0x82;     // GET request
}



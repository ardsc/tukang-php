<?php

function d($foo)
{
    echo "<pre>";
    print_r($foo);
    echo "</pre>";
}

function dd($foo)
{
    d($foo);
    exit();
}

class Request
{
    public static function base()
    {
        return str_replace(
            ["\\", " "],
            ["/", "%20"],
            dirname($_SERVER["SCRIPT_NAME"])
        );
    }

    public static function url()
    {
        $url = str_replace("@", "%40", $_SERVER["REQUEST_URI"]);

        if (strpos($url, "?")) {
            $url = explode("?", $url)[0];
        }

        if (
            self::base() != "/" and
            strlen(self::base()) > 0 and
            strpos($url, self::base()) === 0
        ) {
            return substr($url, strlen(self::base()));
        }

        if (empty($url)) {
            return "/";
        }

        return $url;
    }

    public static function method()
    {
        $method = $_SERVER["REQUEST_METHOD"];

        if (isset($_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"])) {
            $method = $_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"];
        } elseif (isset($_REQUEST["_method"])) {
            $method = $_REQUEST["_method"];
        }

        return strtoupper($method);
    }

    private static function body()
    {
        if (in_array(self::method(), ["POST", "PUT", "PATCH"])) {
            return file_get_contents("php://input");
        }

        return null;
    }

    private static function parseQuery($url)
    {
        $params = [];

        $args = parse_url($url);

        if (isset($args["query"])) {
            parse_str($args["query"], $params);
        }

        return $params;
    }

    public static function query($key = null)
    {
        if (!empty(self::url())) {
            $_GET += self::parseQuery(self::url());
        }

        if ($key) {
            return isset($_GET[$key]) ? $_GET[$key] : null;
        }

        return $_GET;
    }

    public static function data($key = null)
    {
        if (strpos($_SERVER["CONTENT_TYPE"], "application/json") === 0) {
            $body = self::body();

            if ($body != "") {
                $data = json_decode($body, true);

                if ($data != null) {
                    if ($key) {
                        return isset($data[$key]) ? $data[$key] : null;
                    }

                    return $data;
                }
            }
        }

        if ($key) {
            return isset($_POST[$key]) ? $_POST[$key] : null;
        }

        return $_POST;
    }

    public static function file($key = null)
    {
        if ($key) {
            return isset($_FILES[$key]) ? $_FILES[$key] : null;
        }

        return $_FILES;
    }

    public static function bearerToken()
    {
        $headers = null;

        if (isset($_SERVER["Authorization"])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            //Nginx or fast CGI

            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists("apache_request_headers")) {
            $requestHeaders = apache_request_headers();

            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(
                array_map("ucwords", array_keys($requestHeaders)),
                array_values($requestHeaders)
            );

            //print_r($requestHeaders);
            if (isset($requestHeaders["Authorization"])) {
                $headers = trim($requestHeaders["Authorization"]);
            }
        }

        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match("/Bearer\s(\S+)/", $headers, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}

class Router
{
    private $getAction = [];
    private $postAction = [];

    public function get($url, $middleware, $action = null)
    {
        $this->getAction[$url] = [
            "action" => is_null($action) ? $middleware : $action,
            "middleware" => $middleware,
        ];
    }

    public function post($url, $middleware, $action = null)
    {
        $this->postAction[$url] = [
            "action" => is_null($action) ? $middleware : $action,
            "middleware" => $middleware,
        ];
    }

    public function run()
    {
        $url = Request::url();
        $method = Request::method();
        $middleware = null;
        $handler = null;

        if ("GET" == $method and isset($this->getAction[$url])) {
            $handler = $this->getAction[$url]["action"];
            $middleware = $this->getAction[$url]["middleware"];
        } elseif ("POST" == $method and isset($this->postAction[$url])) {
            $handler = $this->postAction[$url]["action"];
            $middleware = $this->postAction[$url]["middleware"];
        }

        if (!is_null($handler)) {
            if (!is_null($middleware)) {
                $result = $middleware($handler, new Request());
            } else {
                $result = $handler(new Request());
            }

            if (is_array($result)) {
                header("content-type:application/json");
                echo json_encode($result);
            } else {
                echo $result;
            }
        } else {
            http_response_code(404);
        }

        exit();
    }
}

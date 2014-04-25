<?php
namespace Actinoids\ApiSuiteBundle\RemoteKernel\Curl\Component;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CookieJar
{
    protected $cookieJar = array();

    /**
     * Sets a cookie.
     *
     * @param Cookie $cookie A Cookie instance
     */
    public function set(Cookie $cookie)
    {
        $this->cookieJar[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
    }

    /**
     * Removes all the cookies from the jar.
     */
    public function clear()
    {
        $this->cookieJar = array();
    }

    /**
     * Gets a cookie by name.
     *
     * You should never use an empty domain, but if you do so,
     * this method returns the first cookie for the given name/path
     * (this behavior ensures a BC behavior with previous versions of
     * Symfony).
     *
     * @param string $name   The cookie name
     * @param string $domain The cookie domain
     * @param string $path   The cookie path
     * @return Cookie|null A Cookie instance or null if the cookie does not exist
     */
    public function get($name, $domain, $path = '/')
    {
        $this->flushExpiredCookies();

        foreach ($this->cookieJar as $cookieDomain => $pathCookies) {

            if ($cookieDomain) {
                $cookieDomain = '.'.ltrim($cookieDomain, '.');
                if ($cookieDomain != substr('.'.$domain, -strlen($cookieDomain))) {
                    continue;
                }
            }

            foreach ($pathCookies as $cookiePath => $namedCookies) {
                if ($cookiePath != substr($path, 0, strlen($cookiePath))) {
                    continue;
                }
                if (isset($namedCookies[$name])) {
                    return $namedCookies[$name];
                }
            }
        }

        return null;
    }

    /**
     * Returns not yet expired cookie values for the given URI.
     *
     * @param string  $uri             A URI
     *
     * @return array An array of cookie values
     */
    public function allByUri($uri)
    {
        $this->flushExpiredCookies();

        $parts = array_replace(array('path' => '/'), parse_url($uri));

        $cookies = array();
        foreach ($this->cookieJar as $domain => $pathCookies) {
            if ($domain) {
                $domain = '.'.ltrim($domain, '.');
                if ($domain != substr('.'.$parts['host'], -strlen($domain))) {
                    continue;
                }
            }

            foreach ($pathCookies as $path => $namedCookies) {
                if ($path != substr($parts['path'], 0, strlen($path))) {
                    continue;
                }

                foreach ($namedCookies as $cookie) {
                    if ($cookie->isSecure() && 'https' != $parts['scheme']) {
                        continue;
                    }

                    $cookies[$cookie->getName()] = $cookie;
                }
            }
        }

        return $cookies;
    }

    /**
     * Returns all not yet expired cookies, regardless of domain
     *
     * @return Cookie[] An array of cookies
     */
    public function all()
    {
        $this->flushExpiredCookies();

        $flattenedCookies = array();
        foreach ($this->cookieJar as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattenedCookies[] = $cookie;
                }
            }
        }
        return $flattenedCookies;
    }

    /**
     * Removes all expired cookies.
     */
    public function flushExpiredCookies()
    {
        foreach ($this->cookieJar as $domain => $pathCookies) {
            foreach ($pathCookies as $path => $namedCookies) {
                foreach ($namedCookies as $name => $cookie) {
                    // Get the UNIX timestamp of expiration
                    $expires = $cookie->getExpiresTime();
                    if ($expires > 0) {
                        // Only check non-session cookies
                        if ($expires < time()) {
                            unset($this->cookieJar[$domain][$path][$name]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Updates the cookie jar from a response Set-Cookie headers.
     *
     * @param array  $setCookies Set-Cookie headers from an HTTP response
     * @param string $uri        The base URL
     */
    public function updateFromSetCookie(array $setCookies, $uri = null)
    {
        $cookies = array();

        foreach ($setCookies as $cookie) {
            foreach (explode(',', $cookie) as $i => $part) {
                if (0 === $i || preg_match('/^(?P<token>\s*[0-9A-Za-z!#\$%\&\'\*\+\-\.^_`\|~]+)=/', $part)) {
                    $cookies[] = ltrim($part);
                } else {
                    $cookies[count($cookies) - 1] .= ','.$part;
                }
            }
        }

        foreach ($cookies as $cookie) {
            try {
                $cookieObj = $this->createCookieFromString($cookie, $uri);
                $this->set($cookieObj);
            } catch (\InvalidArgumentException $e) {
                // Invalid cookies are ignored
            }
        }
    }

    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @param string $cookie A Set-Cookie header value
     * @param string $url    The base URL
     * @return Cookie A Cookie instance
     * @throws \InvalidArgumentException
     */
    public function createCookieFromString($cookie, $uri)
    {
        
        $parts = explode(';', $cookie);

        if (false === strpos($parts[0], '=')) {
            throw new \InvalidArgumentException(sprintf('The cookie string "%s" is not valid.', $parts[0]));
        }


        list($name, $value) = explode('=', array_shift($parts), 2);

        $values = array(
            'name'     => urldecode(trim($name)),
            'value'    => urldecode(trim($value)),
            'expires'  => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => false,
        );

        if (!is_null($uri)) {

            if ((false === $uriParts = parse_url($uri)) || !isset($uriParts['host']) || !isset($uriParts['path'])) {
                throw new \InvalidArgumentException(sprintf('The URL "%s" is not valid.', $uri));
            }

            // Set the default domain and path
            $values['domain'] = $uriParts['host'];
            $values['path'] = substr($uriParts['path'], 0, strrpos($uriParts['path'], '/'));
        }


        foreach ($parts as $part) {
            $part = trim($part);

            if ('secure' === strtolower($part)) {

                // Ignore the secure flag if the original URI is not given or is not HTTPS
                if (!$uri || !isset($uriParts['scheme']) || 'https' != $uriParts['scheme']) {
                    continue;
                }
                $values['secure'] = true;
                continue;
            }

            if ('httponly' === strtolower($part)) {
                $values['httponly'] = true;
                continue;
            }

            if (2 === count($elements = explode('=', $part, 2))) {
                list($k, $v) = $elements;
                switch (strtolower($k)) {
                    case 'expires':
                        $values['expires'] = strtotime($v);
                        break;
                    case 'domain':
                        $values['domain'] = $v;
                        break;
                    case 'path':
                        $values['path'] = $v;
                        break;
                    default:
                        break;
                }
            }
        }
        return new Cookie(
            $values['name'],
            $values['value'],
            $values['expires'],
            $values['path'],
            $values['domain'],
            $values['secure'],
            $values['httponly']
        );
    }

    /**
     * Updates the cookie jar from a Response object.
     *
     * @param Response $response A Response object
     * @param string   $uri      The base URL
     */
    public function updateFromResponse(Response $response, $uri = null)
    {
        if ($response->headers->has('Set-Cookie')) {
            $cookie = $response->headers->get('Set-Cookie');
            if (is_array($cookie)) {
                $cookies = $cookie;
            } else {
                $cookies = array($cookie);
            }
            $this->updateFromSetCookie($cookies, $uri);
        }
        
    }
}

<?php

namespace SocialiteProviders\Reddit;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'REDDIT';

    protected $scopes = ['identity'];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://www.reddit.com/api/v1/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.reddit.com/api/v1/access_token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://oauth.reddit.com/api/v1/me',
            [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer '.$token,
                    'User-Agent'    => $this->getUserAgent(),
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $avatar = null;
        if (! empty($user['icon_img'])) {
            $avatar = $user['icon_img'];

            // Strip the query segment of the URL if it exists.
            // It provides resize attributes that we're not interested in.
            if ($querypos = strpos($avatar, '?')) {
                $avatar = substr($avatar, 0, $querypos);
            }
        }

        $name = null;
        //Check if user has a display name
        if (! empty($user['subreddit']['title'])) {
            $name = $user['subreddit']['title'];
        }

        return (new User)->setRaw($user)->map([
            'id'   => $user['id'], 'nickname' => $user['name'],
            'name' => $name, 'email' => null, 'avatar' => $avatar,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => [
                'Accept'     => 'application/json',
                'User-Agent' => $this->getUserAgent(),
            ],
            RequestOptions::AUTH        => [$this->clientId, $this->clientSecret],
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        $this->credentialsResponseBody = json_decode((string) $response->getBody(), true);

        return $this->credentialsResponseBody;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    protected function getUserAgent()
    {
        return implode(':', [
            $this->getConfig('platform'),
            $this->getConfig('app_id'),
            $this->getConfig('version_string'),
        ]);
    }

    public static function additionalConfigKeys(): array
    {
        return ['platform', 'app_id', 'version_string'];
    }
}

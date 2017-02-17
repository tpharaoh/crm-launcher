<?php

namespace Rubenwouters\CrmLauncher\ApiCalls;

use Rubenwouters\CrmLauncher\Models\Configuration;
use Session;

class FetchTwitterContent
{
    const PUBLIC_TYPE = 'public';
    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @param \Rubenwouters\CrmLauncher\Models\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Get number of followers
     *
     * @return array|\Illuminate\View\View
     */
    public function fetchFollowers()
    {
        try {
            $client = initTwitter();
            $pageId = $this->config->TwitterId();
            $lookup = $client->get('users/show/followers_count.json?user_id=' . $pageId);

            return json_decode($lookup->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());

            return back();
        }
    }

    /**
     * Fetch user's tweets
     *
     * @return array|\Illuminate\View\View
     */
    public function fetchTwitterStats()
    {
        $client = initTwitter();
        $twitterId = $this->config->twitterId();

        try {
            $tweets = $client->get('statuses/user_timeline.json?user_id=' . $twitterId);

            return json_decode($tweets->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());

            return back();
        }
    }

    /**
     * Fetch all mentions
     *
     * @return array|\Illuminate\View\View
     */
    public function fetchMentions()
    {
        $latestMentionId = latestMentionId();

        try {
            $client = initTwitter();

            if ($latestMentionId) {
                $mentions_response = $client->get('statuses/mentions_timeline.json?since_id=' . $latestMentionId);
            } else {
                $mentions_response = $client->get('statuses/mentions_timeline.json?count=1');
            }

            return json_decode($mentions_response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Fetch all direct (private) messages
     *
     * @param integer $sinceId
     *
     * @return array|\Illuminate\View\View
     */
    public function fetchDirectMessages($sinceId)
    {
        $client = initTwitter();

        try {
            if ($sinceId != 0) {
                $response = $client->get('direct_messages.json?full_text=true&since_id=' . $sinceId);
            } else {
                $response = $client->get('direct_messages.json?count=1');
            }

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Get newest mention id on Twitter (Twitter)
     *
     * @return mixed
     */
    public function newestMentionId()
    {
        $client = initTwitter();

        try {
            $mentions = $client->get('statuses/mentions_timeline.json?count=1');
            $mentions = json_decode($mentions->getBody(), true);

            if (count($mentions)) {
                return $mentions[0]['id_str'];
            }

            return false;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());

            return back();
        }
    }

    /**
     * Get newest direct ID on Twitter (Twitter)
     *
     * @return mixed
     */
    public function newestDirectId()
    {
        $client = initTwitter();

        try {
            $directs = $client->get('direct_messages.json?count=1');
            $directs = json_decode($directs->getBody(), true);

            if (count($directs)) {
                return $directs[0]['id_str'];
            }

            return false;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());

            return back();
        }
    }

    /**
     * Answer tweet
     *
     * @param  Request $request
     * @param  string $type
     * @param  integer $toId
     * @param  string $handle
     *
     * @return array|\Illuminate\View\View
     */
    public function answerTweet($request, $type, $toId, $handle)
    {
        $answer = rawurlencode($request->input('answer'));
        $client = initTwitter();

        try {
            if ($type == self::PUBLIC_TYPE) {
                $reply = $client->post('statuses/update.json?status=' . $answer . "&in_reply_to_status_id=" . $toId);
            } else {
                $reply = $client->post('direct_messages/new.json?screen_name=' . $handle . '&text=' . $answer);
            }

            Session::flash('flash_success', trans('crm-launcher::success.tweet_sent'));
            return json_decode($reply->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Publish tweet
     *
     * @param  string $tweet
     *
     * @return array|\Illuminate\View\View
     */
    public function publishTweet($tweet)
    {
        $client = initTwitter();

        try {
            $publishment = $client->post('statuses/update.json?status=' . $tweet);
            return json_decode($publishment->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Delete tweet
     *
     * @param  object $case
     * @param  object $answer
     *
     * @return void|\Illuminate\View\View
     */
    public function deleteTweet($case, $answer)
    {
        $client = initTwitter();

        try {
            if ($case->origin == 'Twitter mention') {
                $client->post('statuses/destroy/' . $answer->tweet_id . '.json');
            } else if ($case->origin == 'Twitter direct') {
                $client->post('direct_messages/destroy.json?id=' . $answer->tweet_id);
            }
            Session::flash('flash_success', trans('crm-launcher::success.tweet_deleted'));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Delete tweet
     *
     * @param  object $case
     * @param  object $answer
     *
     * @return void|\Illuminate\View\View
     */
    public function deleteTweetPublishment($answer)
    {
        $client = initTwitter();

        try {
            $client->post('statuses/destroy/' . $answer->tweet_id . '.json');
            Session::flash('flash_success', trans('crm-launcher::success.tweet_deleted'));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }

    /**
     * Follow/unfollow user
     *
     * @param  object $contact
     * @param  integer $twitterId
     *
     * @return void|\Illuminate\View\View
     */
    public function toggleFollowUser($contact, $twitterId)
    {
        $client = initTwitter();

        try {
            if ($contact->following) {
                $contact->following = 0;
                $client->post('friendships/destroy.json?follow=true&user_id=' . $twitterId);
                Session::flash('flash_success', trans('crm-launcher::success.unfollow'));
            } else {
                $contact->following = 1;
                $client->post('friendships/create.json?follow=true&user_id=' . $twitterId);
                Session::flash('flash_success', trans('crm-launcher::success.follow'));
            }

            $contact->save();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            getErrorMessage($e->getResponse()->getStatusCode());
            return back();
        }
    }
}

<?php

namespace IntegrationOS\AuthKit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AuthKit
{
	private string $secret;
	private array $configs;
	private string $environment;

	function __construct(string $secret, array $configs = [])
	{
		$this->secret = $secret;
		$this->configs = $configs;
		$this->environment = str_contains($secret, "sk_live_") ? "live" : "test";
	}

	private function getUrl(string $type): string
	{
		$services_url = $this->configs["base_url"] ?? "https://api.integrationos.com";

		$api_url = str_contains($services_url, "localhost")
			? "http://localhost:3005"
			: (str_contains($services_url, "development")
				? "https://development-api.integrationos.com"
				: "https://api.integrationos.com");

		return match ($type) {
			"get_settings" => "$services_url/internal/v1/settings/get",
			"create_event_link" => "$services_url/internal/v1/event-links/create",
			"get_connection_definitions" => "$api_url/v1/public/connection-definitions?limit=100",
			"create_embed_token" => "$services_url/internal/v1/embed-tokens/create",
			"get_session_id" => "$services_url/v1/public/generate-id/session_id",
		};
	}

	private function getHeaders(string $type = "buildable"): array
	{
		return match ($type) {
			"buildable" => [
				"X-Buildable-Secret" => $this->secret,
				"Content-Type" => "application/json",
			],
			"ios_secret" => [
				"x-integrationos-secret" => $this->secret
			]
		};
	}

	/**
	 * @throws GuzzleException
	 */
	private function apiCall($method_type, $url, $payload = null, $headers = null): array
	{
		$client = new Client();

		$options = [];
		if ($payload) {
			$options['body'] = json_encode($payload);
		}
		if ($headers) {
			$options['headers'] = $headers;
		}

		$response = $client->request($method_type, $url, $options);

		return json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * @throws GuzzleException
	 */
	private function getSettings(): array
	{
		return $this->apiCall("POST", $this->getUrl("get_settings"), [], $this->getHeaders());
	}

	/**
	 * @throws GuzzleException
	 */
	private function createEventLink(array $payload): array
	{
		return $this->apiCall(
			"POST",
			$this->getUrl("create_event_link"),
			[
				...$payload,
				"environment" => str_starts_with($this->secret, "sk_test") ? "test" : "live",
				"usageSource" => "sdk"
			],
			$this->getHeaders());
	}

	/**
	 * @throws GuzzleException
	 */
	private function getConnectionDefinitions(): array
	{
		return $this->apiCall("GET", $this->getUrl("get_connection_definitions"), [], $this->getHeaders("ios_secret"));
	}

	/**
	 * @throws GuzzleException
	 */
	private function getSessionId(): array
	{
		return $this->apiCall("GET", $this->getUrl("get_session_id"));
	}

	/**
	 * @throws GuzzleException
	 */
	private function createEmbedToken($connected_platforms, $event_links, $settings): array
	{
		$token_payload = [
			"linkSettings" => [
				"connectedPlatforms" => $connected_platforms,
				"eventIncToken" => $event_links["token"]
			],
			"group" => $event_links["group"],
			"label" => $event_links["label"],
			"environment" => str_starts_with($this->secret, "sk_test") ? "test" : "live",
			"expiresAt" => (time() * 1000) + (5 * 1000 * 60),
			"sessionId" => $this->getSessionId()['id'],
			"features" => $settings["features"]
		];

		return $this->apiCall("POST", $this->getUrl("create_embed_token"), $token_payload, $this->getHeaders());
	}

	public function create(array $payload): array|string
	{
		try {
			$settings = $this->getSettings();

			$event_link = $this->createEventLink($payload);

			$connection_definitions = $this->getConnectionDefinitions();

			$active_connection_definitions = isset($connection_definitions["rows"]) ? array_filter($connection_definitions["rows"], fn ($connection_definition) => $connection_definition["active"]) : [];
			$connected_platforms = isset($settings["connectedPlatforms"]) ? array_values(array_filter($settings["connectedPlatforms"],
				fn ($platform) => in_array($platform["connectionDefinitionId"], array_column($active_connection_definitions, "_id")) && $platform["active"] && ($this->environment === "live" ? (isset($platform["environment"]) && $platform["environment"] === "live") : ((isset($platform["environment"]) && $platform["environment"] === 'test') || !isset($platform["environment"]))))) : [];

			return $this->createEmbedToken($connected_platforms, $event_link, $settings);
		} catch (GuzzleException $e) {
			http_response_code(500);
			return [
				"message" => $e->getMessage()
			];
		}
	}
}

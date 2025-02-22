<?php
class CraigslistBridge extends BridgeAbstract {
	const MAINTAINER = 'Yaman Qalieh';
	const NAME = 'Craigslist Bridge';
	const URI = 'https://craigslist.org/';
	const DESCRIPTION = 'Returns craigslist search results';

	const PARAMETERS = array( array(
		'region' => array(
			'name' => 'Region',
			'title' => 'The subdomain before craigslist.org in the URL',
			'exampleValue' => 'sfbay',
			'required' => true
		),
		'search' => array(
			'name' => 'Search Query',
			'title' => 'Everything in the URL after /search/',
			'exampleValue' => 'sya?query=laptop',
			'required' => true
		),
		'limit' => array(
			'name' => 'Number of Posts',
			'type' => 'number',
			'title' => 'The maximum number of posts is 120. Use 0 for unlimited posts.',
			'defaultValue' => '25'
		)
	));

	const TEST_DETECT_PARAMETERS = array(
		'https://sfbay.craigslist.org/search/sya?query=laptop' => array(
			'region' => 'sfbay', 'search' => 'sya?query=laptop'
		),
		'https://newyork.craigslist.org/search/sss?query=32gb+flash+drive&bundleDuplicates=1&max_price=20' => array(
			'region' => 'newyork', 'search' => 'sss?query=32gb+flash+drive&bundleDuplicates=1&max_price=20'
		),
	);

	const URL_REGEX = '/^https:\/\/(?<region>\w+).craigslist.org\/search\/(?<search>.+)/';

	public function detectParameters($url) {
		if(preg_match(self::URL_REGEX, $url, $matches)) {
			$params = array();
			$params['region'] = $matches['region'];
			$params['search'] = $matches['search'];
			return $params;
		}
	}

	public function getURI() {
		if (!is_null($this->getInput('region'))) {
			$domain = 'https://' . $this->getInput('region') . '.craigslist.org/search/';
			return urljoin($domain, $this->getInput('search'));
		}
		return parent::getURI();
	}

	public function collectData() {
		$uri = $this->getURI();
		$html = getSimpleHTMLDOM($uri);

		// Check if no results page is shown (nearby results)
		if ($html->find('.displaycountShow', 0)->plaintext == '0') {
			return;
		}

		// Search for "more from nearby areas" banner in order to skip those results
		$results = $html->find('.result-row, h4.nearby');

		// Limit the number of posts
		if ($this->getInput('limit') > 0) {
			$results = array_slice($results, 0, $this->getInput('limit'));
		}

		foreach($results as $post) {

			// Skip "nearby results" banner and results
			if ($post->tag == 'h4') {
				break;
			}

			$item = array();

			$heading = $post->find('.result-heading a', 0);
			$item['uri'] = $heading->href;
			$item['title'] = $heading->plaintext;
			$item['timestamp'] = $post->find('.result-date', 0)->datetime;
			$item['uid'] = $heading->id;
			$item['content'] = $post->find('.result-price', 0)->plaintext . ' '
							 . $post->find('.result-hood', 0)->plaintext;

			$images = $post->find('.result-image[data-ids]', 0);
			if (!is_null($images)) {
				$item['content'] .= '<br>';
				foreach(explode(',', $images->getAttribute('data-ids')) as $image) {
					// Remove leading 3: from each image id
					$id = substr($image, 2);
					$image_uri = 'https://images.craigslist.org/' . $id . '_300x300.jpg';
					$item['content'] .= '<img src="' . $image_uri . '">';
					$item['enclosures'][] = $image_uri;
				}
			}
			$this->items[] = $item;
		}
	}
}

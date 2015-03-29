<?php
namespace Craft;

/**
 * The service layer and global API into Doxter
 *
 * Class DoxterService
 *
 * @author  Selvin Ortiz - https://selv.in
 * @package Craft
 */

class DoxterService extends BaseApplicationComponent
{
	/**
	 * Parses source markdown into valid html using various rules and parsers
	 *
	 * @param string $source  The markdown source to parse
	 * @param array  $options Passed in parameters via a template filter call
	 *
	 * @return \Twig_Markup The parsed content flagged as safe to output
	 */
	public function parse($source, array $options = array())
	{
		$codeBlockSnippet    = null;
		$addHeaderAnchors    = true;
		$addHeaderAnchorsTo  = array('h1', 'h2', 'h3');
		$startingHeaderLevel = 1;
		$parseReferenceTags  = true;
		$parseShortcodes     = true;

		$options = array_merge(craft()->plugins->getPlugin('doxter')->getSettings()->getAttributes(), $options);

		extract($options);

		// Parsing reference tags first so that we can parse markdown within them
		if ($parseReferenceTags)
		{
			if ($this->onBeforeReferenceTagParsing(compact('source', 'options')))
			{
				$source = DoxterReferenceTagParser::instance()->parse($source);
			}
		}

		if ($parseShortcodes)
		{
			if ($this->onBeforeShortcodeParsing(compact('source')))
			{
				$source = DoxterShortcodeParser::instance()->parse($source);
			}
		}

		if ($this->onBeforeMarkdownParsing(compact('source')))
		{
			$source = \ParsedownExtra::instance()->text($source);
		}

		if ($this->onBeforeCodeBlockParsing(compact('source', 'codeBlockSnippet')))
		{
			$source = DoxterCodeBlockParser::instance()->parse($source, compact('codeBlockSnippet'));
		}

		if ($addHeaderAnchors)
		{
			if ($this->onBeforeHeaderParsing(compact('source', 'addHeaderAnchorsTo')))
			{
				$source = DoxterHeaderParser::instance()->parse($source, compact('addHeaderAnchorsTo', 'startingHeaderLevel'));
			}
		}

		return TemplateHelper::getRaw($source);
	}

	/**
	 * Ensures that a valid list of parseable headers is returned
	 *
	 * @param string $headerString
	 *
	 * @return array
	 */
	public function getHeadersToParse($headerString = '')
	{
		$allowedHeaders = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');

		$headers = ArrayHelper::filterEmptyStringsFromArray(ArrayHelper::stringToArray($headerString));

		if (count($headers))
		{
			foreach ($headers as $key => $header)
			{
				$header = strtolower($header);

				if (!in_array($header, $allowedHeaders))
				{
					unset($headers[$key]);
				}
			}
		}

		return $headers;
	}

	/**
	 * Returns the value of a deeply nested array key by using dot notation
	 *
	 * @param string $key
	 * @param array  $data
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function getValueByKey($key, array $data, $default = null)
	{
		if (!is_string($key) || empty($key) || !count($data))
		{
			return $default;
		}

		if (strpos($key, '.') !== false)
		{
			$keys = explode('.', $key);

			foreach ($keys as $innerKey)
			{
				if (!array_key_exists($innerKey, $data))
				{
					return $default;
				}

				$data = $data[$innerKey];
			}

			return $data;
		}

		return array_key_exists($key, $data) ? $data[$key] : $default;
	}


	/**
	 * Renders a plugin template whether the request is for the control panel or the site
	 *
	 * @param string $template
	 * @param array  $vars
	 *
	 * @return string
	 */
	public function renderPluginTemplate($template, array $vars = array())
	{
		$path     = craft()->path->getTemplatesPath();
		$rendered = null;

		craft()->path->setTemplatesPath(craft()->path->getPluginsPath().'doxter/templates/');

		if (craft()->templates->doesTemplateExist($template))
		{
			$rendered = craft()->templates->render($template, $vars);
		}

		craft()->path->setTemplatesPath($path);

		return $rendered;
	}

	/**
	 * @param array $shortcodes
	 */
	public function registerShortcodes(array $shortcodes)
	{
		DoxterShortcodeParser::instance()->registerShortcodes($shortcodes);
	}

	/**
	 * @param $shortcode
	 * @param $callback
	 */
	public function registerShortcode($shortcode, $callback)
	{
		DoxterShortcodeParser::instance()->registerShortcode($shortcode, $callback);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function onBeforeReferenceTagParsing(array $params = array())
	{
		return $this->raiseOwnEvent(__FUNCTION__, $params);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function onBeforeShortcodeParsing(array $params = array())
	{
		return $this->raiseOwnEvent(__FUNCTION__, $params);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function onBeforeMarkdownParsing(array $params = array())
	{
		return $this->raiseOwnEvent(__FUNCTION__, $params);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function onBeforeCodeBlockParsing(array $params = array())
	{
		return $this->raiseOwnEvent(__FUNCTION__, $params);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function onBeforeHeaderParsing(array $params = array())
	{
		return $this->raiseOwnEvent(__FUNCTION__, $params);
	}

	/**
	 * @param string $name
	 * @param array  $params
	 *
	 * @return bool
	 * @throws \CException
	 */
	protected function raiseOwnEvent($name, array $params = array())
	{
		$event = new Event($this, $params);

		$this->raiseEvent(array_pop(explode('\\', $name)), $event);

		return $event->performAction;
	}
}

<?php
namespace verbb\formie\prosemirror\tohtml\Marks;

use Craft;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\validators\HandleValidator;

class Link extends Mark
{
    protected $markType = 'link';
    protected $tagName = 'a';

    public function tag()
    {
        $attrs = [];

        if (isset($this->mark->attrs->target)) {
            $attrs['target'] = $this->mark->attrs->target;

            if ($attrs['target'] === '_blank') {
                $attrs['rel'] = 'noopener noreferrer nofollow';
            }
        }

        // Parse the link URL for ref tags
        $href = $this->mark->attrs->href ?? '';

        if ($href) {
            $attrs['href'] = self::parseRefTags($href);
        }

        return [
            [
                'tag' => $this->tagName,
                'attrs' => $attrs,
            ],
        ];
    }

    private static function parseRefTags($value)
    {
        $value = preg_replace_callback('/([^\'"\?#]*)(\?[^\'"\?#]+)?(#[^\'"\?#]+)?(?:#|%23)([\w]+)\:(\d+)(?:@(\d+))?(\:(?:transform\:)?' . HandleValidator::$handlePattern . ')?/', function($matches) {
            [, $url, $query, $hash, $elementType, $ref, $siteId, $transform] = array_pad($matches, 10, null);

            // Create the ref tag, and make sure :url is in there
            $ref = $elementType . ':' . $ref . ($siteId ? "@$siteId" : '') . ($transform ?: ':url');

            if ($query || $hash) {
                // Make sure that the query/hash isn't actually part of the parsed URL
                // - someone's Entry URL Format could include "?slug={slug}" or "#{slug}", etc.
                // - assets could include ?mtime=X&focal=none, etc.
                $parsed = Craft::$app->getElements()->parseRefs("{{$ref}}");

                if ($query) {
                    // Decode any HTML entities, e.g. &amp;
                    $query = Html::decode($query);

                    if (mb_strpos($parsed, $query) !== false) {
                        $url .= $query;
                        $query = '';
                    }
                }
                if ($hash && mb_strpos($parsed, $hash) !== false) {
                    $url .= $hash;
                    $hash = '';
                }
            }

            return '{' . $ref . '||' . $url . '}' . $query . $hash;
        }, $value);

        if (StringHelper::contains($value, '{')) {
            $value = Craft::$app->getElements()->parseRefs($value);
        }

        return $value;
    }
}

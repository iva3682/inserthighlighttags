<?php
namespace iva3682\InsertHighLightTags;

/**
 * InsertHighLightTags short summary.
 *
 * InsertHighLightTags description.
 *
 * @version 1.0
 * @author Иван
 */
class InsertHighLightTags
{
    /**
     * Summary of $tagPositions
     * @var TagPosition[]
     */
    private $tagPositions = [];

    /**
     * Summary of $restoreItems
     * @var RestoreProductNameItem[]
     */
    private $restoreItems = [];

    public function __construct() {

    }

    public function addRestoreItem(RestoreProductNameItem $restoreProductNameItem) {
        $this->restoreItems[] = $restoreProductNameItem;
    }

    public function addTag(string $tagName, int $start, int $length) {
        $tag = new Tag($tagName);
        $tapPosition = new TagPosition($tag, $start, $length);

        $this->tagPositions[] = $tapPosition;
    }

    public function build(string $source) {
        usort($this->tagPositions, function (TagPosition $a, TagPosition $b) {
            if ($a->getStart() == $b->getStart()) {
                if ($a->getLength() == $b->getLength()) {
                    return 0;
                }

                return ($a->getLength() < $b->getLength()) ? 1 : -1;
            }

            return ($a->getStart() < $b->getStart()) ? -1 : 1;
        });

        foreach($this->tagPositions as $idx => $tagPosition) {
            $start = $tagPosition->getStart();

            $end = $start + $tagPosition->getLength() + $tagPosition->getTag()->getLengthOpen();

            $source = $this->mb_substr_replace($source, $tagPosition->getTag()->getOpen(), $start, 0);
            $source = $this->mb_substr_replace($source, $tagPosition->getTag()->getClose(), $end, 0);

            for($i = $idx + 1; $i < count($this->tagPositions); $i++) {
                $applyTagPosition = $this->tagPositions[$i];

                if($start <= $applyTagPosition->getStart()) {
                    if($applyTagPosition->getEnd() > $tagPosition->getEnd()) {
                        $applyTagPosition->setStart($applyTagPosition->getStart() + $tagPosition->getTag()->getLengthClose());
                    }

                    $applyTagPosition->setStart($applyTagPosition->getStart() + $tagPosition->getTag()->getLengthOpen());
                }
            }
        }

        return $source;
    }

    public function restore(string $source) {
        $allowSpace = ['', ' '];

        usort($this->restoreItems, function (RestoreProductNameItem $a, RestoreProductNameItem $b) {
            if ($a->getPosition() == $b->getPosition()) {
                return 0;
            }
            return ($a->getPosition() < $b->getPosition()) ? -1 : 1;
        });

        foreach($this->restoreItems as $restoreItem) {
            foreach($this->tagPositions as $tagPositions) {
                if($tagPositions->getStart() >= $restoreItem->getPosition()) {
                    $offsetSpace = $offsetPunct = 0;
                    $leftWrap = $rightWrap = '';

                    if($restoreItem->getPosition() - 1 >= 0) {
                        $leftWrap = mb_substr($source, $restoreItem->getPosition() - 1, 1);
                    }

                    if($restoreItem->getPosition() + $restoreItem->getLength() <= mb_strlen($source)) {
                        $rightWrap = mb_substr($source, $restoreItem->getPosition() + $restoreItem->getLength(), 1);
                    }

                    if(in_array($leftWrap, $allowSpace) and in_array($rightWrap, $allowSpace)) {
                        $offsetSpace = 1;
                    }
                    elseif(!in_array($leftWrap, $allowSpace) and !in_array($rightWrap, $allowSpace)) {
                        $offsetPunct = 1;
                    }

                    $tagPositions->setStart($tagPositions->getStart() + $restoreItem->getLength() + $offsetSpace - $offsetPunct);
                }
            }
        }
    }

    public function extractTags(string $highlight, string $tagName) {
        $tag = new Tag($tagName);

        $offset = 0;
        $tagPosition = [];

        while(($start = mb_strpos($highlight, $tag->getOpen(), $offset)) !== false) {
            $len = mb_strpos($highlight, $tag->getClose()) - $tag->getLengthOpen() - $start;

            $offset = $start + 1;

            $highlight = $this->str_replace_first($tag->getOpen(), '', $highlight);
            $highlight = $this->str_replace_first($tag->getClose(), '', $highlight);

            $tagPosition[] = new TagPosition($tag, $start, $len);
        }

        $this->tagPositions = array_merge($this->tagPositions, $tagPosition);
    }

    private function mb_substr_replace(string $string, string $replacement, int $start, int $length = NULL) {
        preg_match_all('/./us', $string, $smatches);
        preg_match_all('/./us', $replacement, $rmatches);

        if ($length === NULL) $length = mb_strlen($string);

        array_splice($smatches[0], $start, $length, $rmatches[0]);

        return join($smatches[0]);
    }

    private function str_replace_first($from, $to, $content) {
        $from = '/' . preg_quote($from, '/') . '/';
        return preg_replace($from, $to, $content, 1);
    }
}
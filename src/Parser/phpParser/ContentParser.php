<?php
declare(strict_types = 1);
/***
 * Date 24.04.2023
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 */

namespace Cpsync\Parser\phpParser;

use Cpsync\Parser\phpQuery;
use Exception;
use function Cpsync\Parser\pq;

/**
 * Class ContentParser
 * @package Cpsync\Parser\phpParser *
 */
class ContentParser
{
    /**
     * Product content
     * @var array $content
     */
    private array $content = [];

    /**
     * Product ID
     * @var int $productId
     */
    private int $productId;

    /**
     * Init primary data
     * @param array $row
     * @return void
     */
    public function init(array $row): void
    {
        $this->productId = $row['product_id'];
        $this->content = ['link'    => $row['url'],
                          'picture' => $row['picture'],
                          'attrs'   => [],
                          'reviews' => [],
                          'images'  => []];
    }

    /***
     * Parse product data
     * @param int $handle_id
     * @param string $html_code - html code
     * @return array
     * @throws Exception
     */
    public function parseData(int $handle_id, string $html_code): array
    {
        $result_list = [$this->productId => ['status'      => false,
                                             'code'        => 404,
                                             'bind_values' => [],
                                             'message'     => '404 - Product not found',
                                             'content'     => [],]];
        // If product found
        if(!str_contains($html_code, 'error_404')) {
            // Parse html code
            $document = phpQuery::newDocument($html_code);
            $content = $document->find('div#content');

            $script = $document->find('script#__NEXT_DATA__');
            $json = json_decode($script->text(), true);
            $initialState = json_decode($json['props']['pageProps']['initialState'], true);

            $content = pq($content);
            // Product description
            preg_match('#(Note_text__\w+)#', $content->html(), $matches);
            $this->content['description'] = trim($content->find('div.' . $matches[1])->html());
            // Product images
            $this->parseImages($initialState['productCard']['fullProductData']['gallery'] ?? []);
            // Product attributes
            $this->parseAttrs($initialState['productCard']['fullProductData']['attributes'] ?? []);
            // Parse product reviews
            $this->parseReviews($initialState['productCard']['productReviewsData']['reviews']);

            $result_list[$handle_id]['status'] = true;
            $result_list[$handle_id]['code'] = 200;
            $result_list[$handle_id]['message'] = '200 - ok';
            $result_list[$handle_id]['content'] = $this->content;
            $result_list[$handle_id]['bind_values'][':product_id'] = $handle_id;
            $result_list[$handle_id]['bind_values'][':description'] = $this->content['description'];
            $result_list[$handle_id]['bind_values'][':images'] = json_encode($this->content['images']);
            $result_list[$handle_id]['bind_values'][':attrs'] = json_encode($this->content['attrs']);
            $result_list[$handle_id]['bind_values'][':reviews'] = json_encode($this->content['reviews']);
        }
        $result_list[$handle_id]['status'] = $this->checkContent();
        return $result_list;
    }


    /***
     * Parse product reviews
     * @param array $reviews
     * @return void
     */
    private function parseReviews(array $reviews): void
    {
        foreach($reviews as $reviews_item) {
            $this->content['reviews'][] = ['rating' => $reviews_item['rating'],
                                           'author' => $reviews_item['name'],
                                           'review' => $reviews_item['summary'],
                                           'date'   => $reviews_item['dateTimestamp'],
                                           'plus'   => $reviews_item['positives'],
                                           'minus'  => $reviews_item['negatives']];
        }
    }

    /***
     * Parse product attrs
     * @param array $groupItems
     * @return void
     */
    private function parseAttrs(array $groupItems): void
    {
        $groupItems = array_column($groupItems, 'groupItems');
        if(is_array($groupItems)) {
            for($i = 0; $i < count($groupItems); $i++) {
                if(isset($groupItems[$i]['name'], $groupItems[$i]['value'])) {
                    $this->content['attrs'][] = ['name'  => trim($groupItems[$i]['name']),
                                                 'value' => trim($groupItems[$i]['value'])];
                }
                else {
                    $groupItems = array_merge($groupItems, $groupItems[$i]);
                    $groupItems = array_values($groupItems);
                    unset($groupItems[$i]);
                }
            }
        }
        /*
        foreach($content->find('dl') as $attr_item) {
             $attr_item = pq($attr_item);
             $this->content['attrs'][] = ['name'  => trim($attr_item->find('dt')->text()),
                                          'value' => trim($attr_item->find('dd')->text())];
         }*/
    }

    /***
     * Parse product images
     * @param array $gallery
     * @return void
     */
    private function parseImages(array $gallery): void
    {
        $this->content['images'] = array_column($gallery, 'fullSize');
    }

    /***
     * Check if product has content
     * @return bool
     */
    private function checkContent(): bool
    {
        return (is_array($this->content['attrs']) || is_array($this->content['reviews']) || is_array($this->content['images']) || strlen($this->content['description']));
    }
}
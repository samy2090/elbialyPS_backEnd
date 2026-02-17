<?php

return [
    'post_body_max_length' => (int) env('POST_BODY_MAX_LENGTH', 700),
    'comment_body_max_length' => (int) env('COMMENT_BODY_MAX_LENGTH', 500),
    'post_rate_limit_per_hour' => (int) env('POST_RATE_LIMIT_PER_HOUR', 5),
    'post_images_max_count' => (int) env('POST_IMAGES_MAX_COUNT', 4),
    'post_image_max_size_mb' => (int) env('POST_IMAGE_MAX_SIZE_MB', 20),
    'post_image_allowed_mimes' => ['jpeg', 'png', 'gif', 'webp'],
    'feed_per_page' => (int) env('POST_FEED_PER_PAGE', 10),
    'comments_per_page' => (int) env('POST_COMMENTS_PER_PAGE', 10),
];

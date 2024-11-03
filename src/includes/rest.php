<?php
/**
* Additional rest API routes
*/
// add_action(
//     'rest_api_init', function() {
//         register_rest_route(
//             'shorthand_connect/v1', '/stories', array(
//                 'methods' => WP_REST_Server::CREATABLE,
//                 'callback' => function() {
//                     return 'Hello World';
//                 },
//             )
//         );
//         register_rest_route(
//             'shorthand_connect/v1', '/publish', array(
//                 'methods' => "POST",
//                 'callback' => function() {
//                     if (!isset($_POST['story_id'])) {
//                         return new WP_Error('missing_story_id', 'Missing story ID query param', array('status' => 400));
//                     }

//                     $story_id = sanitize_text_field( $_POST['story_id'] );

//                     // $posts = get_posts(
//                     //     array(
//                     //         'post_type' => 'shorthand_story',
//                     //         'post_status' => 'any',
//                     //         'meta_query' => array(
//                     //             array(
//                     //                 'key' => 'story_id',
//                     //                 'value' => $story_id,
//                     //             ),
//                     //         ),
//                     //         'orderby' => 'modified',
//                     //         'numberposts' => '1',
//                     //     )
//                     // );



//                 },
//                 'permissions_callback' => function() {
//                     return current_user_can('edit_posts');
//                 }
//             )
//         );
//         register_rest_route(
//             'shorthand_connect/v1', '/stories', array(
//                 'methods' => WP_REST_Server::READABLE,
//                 'callback' => function() {
//                     return get_posts(
//                         array(
//                             'post_type' => 'shorthand_story',
//                             'post_status' => 'any',
//                             // 'meta_query' => array(
//                             //     array(
//                             //         'key' => 'story_id'
//                             //     )
//                             // )
//                         )
//                     );
//                 },
//             )
//         );
//     }
// );
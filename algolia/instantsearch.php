<?php
/**
 * WP Search With Algolia instantsearch template file.
 *
 * @author  WebDevStudios <contact@webdevstudios.com>
 * @since   1.0.0
 *
 * @package WebDevStudios\WPSWA
 */

get_header();

?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title">Search Results</h1></div>
					<div class="col-2 icon plain"><span role="img" aria-label="Search Results" title="Search Results" class="taxonomy-svg 404"><?php echo lwtv_symbolicons( 'search.svg', 'fa-search' ); ?></span></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<div id="algolia-search-box">
							<div id="algolia-stats"></div>
							<svg class="search-icon" width="25" height="25" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><path d="M24.828 31.657a16.76 16.76 0 0 1-7.992 2.015C7.538 33.672 0 26.134 0 16.836 0 7.538 7.538 0 16.836 0c9.298 0 16.836 7.538 16.836 16.836 0 3.22-.905 6.23-2.475 8.79.288.18.56.395.81.645l5.985 5.986A4.54 4.54 0 0 1 38 38.673a4.535 4.535 0 0 1-6.417-.007l-5.986-5.986a4.545 4.545 0 0 1-.77-1.023zm-7.992-4.046c5.95 0 10.775-4.823 10.775-10.774 0-5.95-4.823-10.775-10.774-10.775-5.95 0-10.775 4.825-10.775 10.776 0 5.95 4.825 10.775 10.776 10.775z" fill-rule="evenodd"></path></svg>
						</div>
						<div id="algolia-hits"></div>
						<div id="algolia-pagination"></div>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4 site-sidebar">

				<div class="widget-wrap">
					<aside class="widget widget_ais widget_tag_cloud">
						<section class="ais-facets" id="facet-post-types"></section>
					</aside>
				</div>
			</div><!-- .col-sm-4 -->

		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<script type="text/html" id="tmpl-instantsearch-hit">
	<article itemtype="http://schema.org/Article">
		<# if ( data.images.thumbnail ) { #>
		<div class="ais-hits--thumbnail">
			<a href="{{ data.permalink }}" title="{{ data.post_title }}">
				<img src="{{ data.images.thumbnail.url }}" alt="{{ data.post_title }}" title="{{ data.post_title }}" itemprop="image" />
			</a>
		</div>
		<# } #>

		<div class="ais-hits--content">
			<h2 itemprop="name headline"><a href="{{ data.permalink }}" title="{{ data.post_title }}" itemprop="url">{{{ data._highlightResult.post_title.value }}}</a></h2>
			<div class="excerpt">
				<p>
		<# if ( data._snippetResult['content'] ) { #>
		  <span class="suggestion-post-content">{{{ data._snippetResult['content'].value }}}</span>
		<# } #>
				</p>
			</div>
		</div>
		<div class="ais-clearfix"></div>
	</article>
</script>

<script type="text/javascript">
	jQuery(function() {
		if(jQuery('#algolia-search-box').length > 0) {

			if (algolia.indices.searchable_posts === undefined && jQuery('.admin-bar').length > 0) {
				alert('It looks like you haven\'t indexed the searchable posts index. Please head to the Indexing page of the Algolia Search plugin and index it.');
			}

			/* Instantiate instantsearch.js */
			var search = instantsearch({
				appId: algolia.application_id,
				apiKey: algolia.search_api_key,
				indexName: algolia.indices.searchable_posts.name,
				urlSync: {
					mapping: {'q': 's'},
					trackedParameters: ['query']
				},
				searchParameters: {
					facetingAfterDistinct: true,
		highlightPreTag: '__ais-highlight__',
		highlightPostTag: '__/ais-highlight__'
				}
			});

			/* Search box widget */
			search.addWidget(
				instantsearch.widgets.searchBox({
					container: '#algolia-search-box',
					placeholder: 'Search for...',
					wrapInput: false,
					poweredBy: algolia.powered_by_enabled
				})
			);

			/* Stats widget */
			search.addWidget(
				instantsearch.widgets.stats({
					container: '#algolia-stats'
				})
			);

			/* Hits widget */
			search.addWidget(
				instantsearch.widgets.hits({
					container: '#algolia-hits',
					hitsPerPage: 10,
					templates: {
						empty: 'No results were found for "<strong>{{query}}</strong>".',
						item: wp.template('instantsearch-hit')
					},
					transformData: {
						item: function (hit) {

							function replace_highlights_recursive (item) {
							  if( item instanceof Object && item.hasOwnProperty('value')) {
								  item.value = _.escape(item.value);
								  item.value = item.value.replace(/__ais-highlight__/g, '<em>').replace(/__\/ais-highlight__/g, '</em>');
							  } else {
								  for (var key in item) {
									  item[key] = replace_highlights_recursive(item[key]);
								  }
							  }
							  return item;
							}

							hit._highlightResult = replace_highlights_recursive(hit._highlightResult);
							hit._snippetResult = replace_highlights_recursive(hit._snippetResult);

							return hit;
						}
					}
				})
			);

			/* Pagination widget */
			search.addWidget(
				instantsearch.widgets.pagination({
					container: '#algolia-pagination'
				})
			);

			/* Post types refinement widget */
			search.addWidget(
				instantsearch.widgets.menu({
					container: '#facet-post-types',
					attributeName: 'post_type_label',
					sortBy: ['isRefined:desc', 'count:desc', 'name:asc'],
					limit: 10,
					templates: {
						header: '<h2 class="widget-title">Post Type</h2>'
					},
				})
			);

			/* Start */
			search.start();

			jQuery('#algolia-search-box input').attr('type', 'search').select();
		}
	});
</script>

<?php

get_footer();

Shortbread
==========

A self-hosted short URL plugin for Wordpress. See http://labs.andrewhayward.net/wordpress/shortbread/ for more.


Installation
------------

Drop the `shortbread` folder into `wp-content/plugins`, and activate through the admin interface. Like you do.

Configure *Shortbread* under `Settings > Short URLs`. Yes, it would arguably make more sense to put these settings on the Permalink page, but given that there's been a bug [1] open about that for four years now, it'll just have to wait.


[1] http://core.trac.wordpress.org/ticket/9296


Usage
-----

Shortbread introduces a new function -- `has_shortlink()` -- which tries to indicate whether a short URL is available in your current context. This allows you to put things like this in your templates.

    <?php if (has_shortlink()): ?>
    <link rel="shortlink" href="<?php echo wp_get_shortlink(); ?>">
    <?php endif; ?>

Otherwise, just use `wp_get_shortlink()` [2]  or `the_shortlink()` as before. Shortbread tries to stay out of the way as much as possible.

[2] http://codex.wordpress.org/Function_Reference/wp_get_shortlink
[3] http://codex.wordpress.org/Function_Reference/the_shortlink


License
-------

As per WordPress's thoughts on the matter, this plugin is released under the GPL [4].


[4] http://www.gnu.org/licenses/gpl.html

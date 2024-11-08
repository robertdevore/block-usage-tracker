jQuery(document).ready(function ($) {
    $('.view-details-button').on('click', function (e) {
        e.preventDefault();

        let blockName = $(this).data('block');
        let postsData = $(this).data('posts'); // Directly access data without JSON.parse()
        let modalBody = $('#block-usage-modal-body');

        // Clear the modal content from any previous usage
        modalBody.empty();

        // Display a summary message at the top
        let usageCount = postsData.length;
        modalBody.append('<p><strong>The ' + blockName + ' block was found ' + usageCount + ' times in the following content:</strong></p>');

        // Filter unique URLs to avoid duplicates
        let uniquePosts = [];
        let uniqueUrls = new Set();

        postsData.forEach(post => {
            if (!uniqueUrls.has(post.url)) {
                uniqueUrls.add(post.url);
                uniquePosts.push(post);
            }
        });

        // Display each unique post
        if (uniquePosts.length > 0) {
            uniquePosts.forEach(post => {
                modalBody.append('<p><a href="' + post.url + '" target="_blank">' + post.title + '</a></p>');
            });
        } else {
            modalBody.append('<p>No posts found for this block.</p>');
        }

        // Show the modal
        $('#block-usage-modal').fadeIn();
    });

    // Close the modal when the close button is clicked
    $('.block-usage-close').on('click', function () {
        $('#block-usage-modal').fadeOut();
    });

    // Close the modal when clicking outside of the modal content
    $(window).on('click', function (e) {
        if ($(e.target).is('#block-usage-modal')) {
            $('#block-usage-modal').fadeOut();
        }
    });
});

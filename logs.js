jQuery(document).ready(function($) {
    function fetchLogs() {
        $.ajax({
            url: mpwrAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'mpwr_fetch_logs',
                nonce: mpwrAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $logContainer = $('#mpwr-log-container');
                    $logContainer.empty();
                    
                    response.data.forEach(function(log) {
                        $logContainer.append($('<div>').text(log));
                    });
                    
                    // auto-scroll to bottom
                    $logContainer.scrollTop($logContainer[0].scrollHeight);
                }
            }
        });
    }

    // fetch logs every 5 seconds
    setInterval(fetchLogs, 5000);
    
    // initial fetch
    fetchLogs();
});

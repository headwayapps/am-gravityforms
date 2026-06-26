jQuery(document).ready(function($) {
    var statusEl = document.getElementById('gf-document-status');
    if (!statusEl) {
        return;
    }

    var jobId = statusEl.getAttribute('data-job-id');
    var entryId = statusEl.getAttribute('data-entry-id');
    var isTempJob = statusEl.getAttribute('data-temp-loading') === '1';

    if (isTempJob) {
        setTimeout(function() { checkDocumentStatus(jobId); }, 1000);
    } else {
        checkDocumentStatus(jobId);
    }

    function checkDocumentStatus(jobId) {
        // If it's a temp job, first try to get the real job ID
        if (jobId.indexOf('temp_') === 0) {
            $.ajax({
                url: gf_docgen_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gf_docgen_get_real_job_id',
                    entry_id: entryId,
                    nonce: gf_docgen_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.job_id) {
                        checkDocumentStatus(response.data.job_id);
                    } else {
                        setTimeout(function() {
                            checkDocumentStatus(jobId);
                        }, 2000);
                    }
                },
                error: function() {
                    setTimeout(function() {
                        checkDocumentStatus(jobId);
                    }, 2000);
                }
            });
            return;
        }

        // Check status of real job ID
        $.ajax({
            url: gf_docgen_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gf_docgen_check_status',
                job_id: jobId,
                nonce: gf_docgen_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var status = response.data.status;

                    if (status === 'completed' && response.data.document_url) {
                        var urlParts = response.data.document_url.split('/');
                        var filename = urlParts[urlParts.length - 1];
                        $('#gf-document-download-link').attr('href', response.data.document_url).attr('download', filename);
                        $('.gf-document-loading').hide();
                        $('#gf-download-ready').show();
                    } else if (status === 'failed') {
                        $('.gf-document-loading').hide();
                        $('#gf-download-error').show();
                    } else {
                        setTimeout(function() {
                            checkDocumentStatus(jobId);
                        }, 3000);
                    }
                } else {
                    setTimeout(function() {
                        checkDocumentStatus(jobId);
                    }, 5000);
                }
            },
            error: function() {
                setTimeout(function() {
                    checkDocumentStatus(jobId);
                }, 5000);
            }
        });
    }
});

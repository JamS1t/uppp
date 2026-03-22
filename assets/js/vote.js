document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vote-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var submissionId = this.dataset.id;
            var voteType = this.dataset.type;

            var formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('vote_type', voteType);

            fetch('vote.php', {
                method: 'POST',
                body: formData,
            })
                .then(function (res) {
                    if (res.status === 401) {
                        window.location.href = 'login.php';
                        return;
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (!data) return;

                    // Update all score displays for this submission
                    document.querySelectorAll('.vote-score[data-id="' + submissionId + '"]').forEach(function (el) {
                        el.textContent = data.score;
                    });

                    // Update active states for all vote buttons for this submission
                    document.querySelectorAll('.vote-btn[data-id="' + submissionId + '"]').forEach(function (voteBtn) {
                        voteBtn.classList.remove('active');
                        if (parseInt(voteBtn.dataset.type) === data.user_vote) {
                            voteBtn.classList.add('active');
                        }
                    });
                })
                .catch(function (err) {
                    console.error('Vote error:', err);
                });
        });
    });
});

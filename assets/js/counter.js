/***************Counter***************/
$(document).ready(function () {
    var timeleft = 49;
    var countdownTimer;

    function startTimer() {
        clearInterval(countdownTimer);
        timeleft = 49;
        $('#countdowntimer').text(timeleft);
        countdownTimer = setInterval(function () {
            timeleft--;
            $('#countdowntimer').text(timeleft);
            if (timeleft <= 0) {
                clearInterval(countdownTimer);
            }
        }, 1000);
    }
    startTimer();
});
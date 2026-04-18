$(document).ready(function () {
    $("#loginForm").on("submit", function (e) {
        e.preventDefault();

        $.ajax({
            url: "login_action.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function (res) {
                if (res.status === "success") {
                    $("#message")
                        .removeClass("text-danger")
                        .addClass("text-success")
                        .text(res.message);

                    window.location.href = res.redirect;
                } else {
                    $("#message")
                        .removeClass("text-success")
                        .addClass("text-danger")
                        .text(res.message);
                }
            },
            error: function () {
                $("#message")
                    .removeClass("text-success")
                    .addClass("text-danger")
                    .text("系統錯誤，請稍後再試");
            }
        });
    });
});
$(document).ready(function () {
    $("#registerForm").attr("novalidate", "novalidate");

    $("#register_username").on("blur", function () {
        let username = $(this).val();

        $.ajax({
            url: "check_user.php",
            type: "POST",
            data: { username: username },
            success: function (res) {
                $("#registerMessage").html(res);
            }
        });
    });

    $("#registerForm").on("submit", function (e) {
        e.preventDefault();
        const memberGroup = $("#registerForm input[name='member_group']:checked").val();
        const username = $("#register_username").val();
        const password = $("#register_password").val();
        const confirmPassword = $("#register_confirm_password").val();
        const agreed = $("#registerForm input[name='agree_terms']").is(":checked");


        if(!memberGroup ){
            $("#registerMessage").css("color", "red").html("Please select a member group");
            return;
        }

        if (username === '' || password === '' || confirmPassword === '') {
            $("#registerMessage").css("color", "red").html("Please fill in all required fields.");
            return;
        }

        if(username.length < 3) {
            $("#registerMessage").css("color", "red").html("Username must be at least 3 characters long.");
            return;
        }

        if(username.length > 16) {
            $("#registerMessage").css("color", "red").html("Username must be less than 16 characters long.");
            return;
        }

        if (password.length < 8) {
            $("#registerMessage").css("color", "red").html("Password must be at least 8 characters long.");
            return;
        }

        if (password !== confirmPassword) {
            $("#registerMessage").css("color", "red").html("Passwords do not match.");
            return;
        }

        if (!agreed) {
            $("#registerMessage").css("color", "red").html("Please agree to the terms and conditions.");
            return;
        }

        $.ajax({
            url: "register_action.php",
            type: "POST",
            data: $(this).serialize(),
            success: function (res) {
                $("#registerMessage").css("color", "green").html(res);
                window.alert("註冊成功，跳轉至首頁");
                window.location.href = "index.php";
            }
        });
    });

    $("#loginForm").on("submit", function (e) {
        e.preventDefault();

        $.ajax({
            url: "login_action.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function (res) {
                if (res.status === "success") {
                    $("#loginMessage").css("color", "green").html(res.message);
                    window.alert("登入成功");
                    window.location.href = res.redirect;
                } else {
                    $("#loginMessage").css("color", "red").html(res.message);
                }
            },
            error: function (xhr, status, error) {
                console.log("status:", status);
                console.log("error:", error);
                console.log("response:", xhr.responseText);
                $("#loginMessage").css("color", "red").html("系統錯誤");
            }
        });
    });
});
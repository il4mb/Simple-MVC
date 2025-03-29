$("#login-form").on("submit", function (e) {
    e.preventDefault();

    const submitBtn = $("[type=submit]");
    const initialText = submitBtn.html();

    // Disable the button and show a loading spinner
    submitBtn.attr("disabled", true).html(
        `<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading...`
    );

    const formData = Object.fromEntries(new FormData(this));

    fetch(config.pathOffset + "/api/v1/auth/login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(formData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                toast("Success", data.message, { color: "success" });
                setTimeout(() => window.location.reload(), 1000);
            } else {
                toast("Error", data.message, { color: "danger" });
            }
        })
        .catch(error => {
            toast("Error", "An error occurred: " + error.message, { color: "danger" });
        })
        .finally(() => {
            // Re-enable the button and reset the text
            setTimeout(() => {
                submitBtn.attr("disabled", false).html(initialText);
            }, 800);
        });
});

const formatTimeAgo = (time) => {
    const diff = Math.floor((Date.now() - time) / 1000);
    if (diff < 2) return "just now";
    if (diff < 60) return `${diff} second${diff > 1 ? "s" : ""} ago`;
    const minutes = Math.floor(diff / 60);
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? "s" : ""} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days > 1 ? "s" : ""} ago`;
};


window.toast = (title, body, options) => {
    options = Object.assign({
        delay: 10000,
        color: "primary"
    }, options)
    const time = Date.now();
    const toastId = `toast-${time}`;
    const $toast = $(`<div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
        <i class="fa-regular fa-bell text-${options.color}"></i>
        <strong class="ms-2 me-auto text-${options.color}">${title}</strong>
        <small class="text-body-secondary timestamp">just now</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
        ${body}
    </div>
</div>`);

    $(".toast-container").append($toast);
    $(`#${toastId}`).toast({ delay: options.delay }).toast("show");
    $toast[0].addEventListener('hidden.bs.toast', () => {
        $(`#${toastId} .timestamp`).remove();
    })

    const intervalId = setInterval(() => {
        const $timestamp = $(`#${toastId} .timestamp`);
        if ($timestamp.length) {
            $timestamp.text(formatTimeAgo(time));
        } else {
            clearInterval(intervalId);
        }
    }, 1000);
};

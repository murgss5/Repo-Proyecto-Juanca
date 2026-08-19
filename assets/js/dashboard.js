const menuButton = document.getElementById("menuButton");
const sidebar = document.getElementById("sidebar");

if (menuButton && sidebar) {
    menuButton.addEventListener("click", function () {

        sidebar.classList.toggle("show");

    });

document.addEventListener("click", function (event) {
        if (
            window.innerWidth < 992 &&
            !sidebar.contains(event.target) &&
            !menuButton.contains(event.target)
        ) {

            sidebar.classList.remove("show");

        }

    });

}
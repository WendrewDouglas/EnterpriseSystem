document.addEventListener("DOMContentLoaded", function () {
    console.log("JavaScript carregado!");

    // Alternar Modo Escuro
    const themeToggle = document.getElementById("theme-toggle");
    const body = document.body;

    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark-mode");
        themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
    }

    themeToggle.addEventListener("click", function () {
        body.classList.toggle("dark-mode");

        if (body.classList.contains("dark-mode")) {
            localStorage.setItem("theme", "dark");
            themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
        } else {
            localStorage.setItem("theme", "light");
            themeToggle.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
        }
    });

    // Alternar Sidebar
    const toggleSidebar = document.querySelector(".toggle-sidebar");
    const sidebar = document.querySelector(".sidebar");
    const topBar = document.querySelector(".top-bar");
    const content = document.querySelector(".content");

    toggleSidebar.addEventListener("click", function () {
        sidebar.classList.toggle("collapsed");
        topBar.classList.toggle("collapsed");
        content.classList.toggle("collapsed");
    });

    // Verifica cliques nos botões da barra superior
    document.querySelectorAll(".top-bar button").forEach(button => {
        button.addEventListener("click", function () {
            console.log(`Botão ${this.innerHTML.trim()} clicado!`);
        });
    });
});

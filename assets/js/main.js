document.addEventListener("DOMContentLoaded", () => {
  // === Theme Toggle ===
  const toggle = document.getElementById("toggleTheme");
  const body = document.body;
  const currentTheme = localStorage.getItem("theme");
  
  if (currentTheme === "dark") {
    body.classList.add("dark");
    if (toggle) toggle.checked = true;
  }
  
  if (toggle) {
    toggle.addEventListener("change", () => {
      if (toggle.checked) {
        body.classList.add("dark");
        localStorage.setItem("theme", "dark");
      } else {
        body.classList.remove("dark");
        localStorage.setItem("theme", "light");
      }
    });
  }
});
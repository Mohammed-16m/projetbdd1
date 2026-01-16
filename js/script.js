// =========================================
// 1. GESTION DES MODALES
// =========================================
function openModal() {
    const modal = document.getElementById('examModal');
    if(modal) {
        modal.style.display = 'flex';
        modal.style.backdropFilter = 'blur(15px)';
    }
}

function closeModal() {
    const modal = document.getElementById('examModal');
    if(modal) {
        modal.style.display = 'none';
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('examModal');
    if (event.target == modal) closeModal();
}

// =========================================
// 2. ALGORITHME D'OPTIMISATION (Lien PHP réel)
// =========================================
function simulerGeneration() {
    const btn = event.target;
    const progressBar = document.getElementById('algo-progress');
    const statusText = document.getElementById('statusText');
    const progressContainer = document.getElementById('progress-container');
    
    if (btn.disabled) return;

    // Afficher le conteneur de progression s'il est caché
    if(progressContainer) progressContainer.style.display = 'block';

    btn.disabled = true;
    btn.style.opacity = "0.7";
    btn.innerHTML = `⏳ Calcul en cours...`;

    let progress = 0;
    
    const interval = setInterval(() => {
        if (progress >= 90) {
            clearInterval(interval);
            
            // Phase 2 : Appel réel au fichier PHP que nous avons créé
            // Attention : j'ai mis 'generer_edt.php' pour correspondre à ton fichier
            fetch('generer_edt.php')
                .then(response => {
                    // Si le PHP fait une redirection simple, on recharge la page
                    window.location.href = 'admin.php?success=1';
                })
                .catch(error => {
                    statusText.innerText = "Erreur de connexion au serveur.";
                    statusText.style.color = "#ff4757";
                    btn.disabled = false;
                    btn.innerHTML = "Réessayer";
                });
        } else {
            progress += 5; 
            if(progressBar) progressBar.style.width = progress + "%";
            
            if(statusText) {
                if(progress < 30) statusText.innerText = "Analyse des capacités des amphis...";
                else if(progress < 60) statusText.innerText = "Vérification des chevauchements...";
                else statusText.innerText = "Finalisation du planning optimal...";
            }
        }
    }, 100); 
}

// =========================================
// 3. RECHERCHE / FILTRAGE
// =========================================
function filtrerTableau() {
    const input = document.querySelector('.search-bar input');
    if(!input) return;
    
    const filter = input.value.toUpperCase();
    const table = document.querySelector("table");
    const tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let textContent = tr[i].textContent || tr[i].innerText;
        tr[i].style.display = textContent.toUpperCase().indexOf(filter) > -1 ? "" : "none";
    }
}
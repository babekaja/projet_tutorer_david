// Vue.js pour la page de réservation
const { createApp } = Vue;

createApp({
    data() {
        return {
            selectedTrajet: '',
            trajetDetails: null,
            acceptConditions: false,
            isLoading: false
        }
    },
    computed: {
        canSubmit() {
            return this.selectedTrajet && this.acceptConditions && !this.isLoading;
        }
    },
    methods: {
        updateTrajetDetails() {
            if (this.selectedTrajet) {
                const select = document.getElementById('trajet_id');
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption.dataset.trajet) {
                    this.trajetDetails = JSON.parse(selectedOption.dataset.trajet);
                }
            } else {
                this.trajetDetails = null;
            }
        },
        
        formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            };
            return date.toLocaleDateString('fr-FR', options);
        },
        
        formatPrice(price) {
            return new Intl.NumberFormat('fr-FR').format(price);
        },
        
        validateForm(event) {
            const form = event.target;
            
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else if (!this.canSubmit) {
                event.preventDefault();
                alert('Veuillez remplir tous les champs requis.');
            } else {
                this.isLoading = true;
                // Le formulaire sera soumis normalement
            }
            
            form.classList.add('was-validated');
        }
    },
    
    mounted() {
        // Si un trajet est présélectionné, charger ses détails
        if (this.selectedTrajet) {
            this.updateTrajetDetails();
        }
        
        // Animation d'entrée
        document.querySelector('.card').classList.add('fade-in-up');
    }
}).mount('#app');
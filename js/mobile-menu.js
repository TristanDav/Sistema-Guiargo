// Script para manejar el menú móvil y desktop
(function() {
    'use strict';
    
    // Variables globales
    let menuToggle, sidebar, sidebarOverlay, mainContent;
    
    // Función para verificar si estamos en móvil
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Función para abrir el sidebar
    function openSidebar() {
        if (!sidebar) return;
        
        if (isMobile()) {
            // En móviles: mostrar overlay y sidebar
            sidebar.classList.add('open');
            sidebar.style.transform = 'translateX(0)';
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
        } else {
            // En desktop: solo mostrar sidebar (si estaba oculto)
            sidebar.classList.remove('hidden');
            sidebar.style.transform = '';
            sidebar.style.display = '';
            if (mainContent) {
                mainContent.style.marginLeft = '280px';
            }
        }
    }
    
    // Función para cerrar el sidebar
    function closeSidebar() {
        if (!sidebar) return;
        
        if (isMobile()) {
            // En móviles: ocultar sidebar y overlay
            sidebar.classList.remove('open');
            sidebar.style.transform = 'translateX(-100%)';
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        } else {
            // En desktop: ocultar sidebar y ajustar contenido
            sidebar.classList.add('hidden');
            if (mainContent) {
                mainContent.style.marginLeft = '0';
            }
        }
    }
    
    // Función para inicializar el sidebar
    function initSidebar() {
        menuToggle = document.getElementById('menuToggle');
        sidebar = document.querySelector('.sidebar');
        sidebarOverlay = document.getElementById('sidebarOverlay');
        mainContent = document.querySelector('.main-content');
        
        // Si no existe el sidebar, salir
        if (!sidebar) {
            return;
        }
        
        // Toggle del sidebar cuando se hace clic en el botón hamburguesa
        if (menuToggle && !menuToggle.hasAttribute('data-initialized')) {
            menuToggle.setAttribute('data-initialized', 'true');
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (isMobile()) {
                    // En móviles: toggle normal
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                } else {
                    // En desktop: toggle mostrar/ocultar
                    if (sidebar.classList.contains('hidden')) {
                        openSidebar();
                    } else {
                        closeSidebar();
                    }
                }
            });
        }
        
        // Cerrar sidebar cuando se hace clic en el overlay (solo móviles)
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        }
        
        // Cerrar sidebar cuando se hace clic en un enlace del menú (solo en móviles)
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(function(item) {
            item.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        });
        
        // Manejar redimensionamiento de ventana
        window.addEventListener('resize', function() {
            if (isMobile()) {
                // En móviles: asegurar que el sidebar esté oculto por defecto
                if (sidebar && !sidebar.classList.contains('open')) {
                    sidebar.style.transform = 'translateX(-100%)';
                }
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                }
            } else {
                // En desktop: mostrar sidebar si no está oculto manualmente
                if (sidebar && !sidebar.classList.contains('hidden')) {
                    sidebar.style.transform = '';
                    if (mainContent) {
                        mainContent.style.marginLeft = '280px';
                    }
                }
                // Cerrar overlay si existe
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
                document.body.style.overflow = '';
            }
        });
        
        // Inicializar estado según el tamaño de pantalla (solo si no está inicializado)
        if (!sidebar.hasAttribute('data-initialized')) {
            sidebar.setAttribute('data-initialized', 'true');
            if (isMobile()) {
                // En móviles: ocultar sidebar por defecto
                sidebar.classList.remove('open');
                sidebar.style.transform = 'translateX(-100%)';
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
                document.body.style.overflow = '';
            } else {
                // En desktop: mostrar sidebar por defecto
                sidebar.classList.remove('hidden');
                sidebar.classList.remove('open');
                sidebar.style.transform = '';
                sidebar.style.display = '';
                if (mainContent) {
                    mainContent.style.marginLeft = '280px';
                }
            }
        }
    }
    
    // Ejecutar cuando el DOM esté listo
    function startInit() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            // DOM ya está listo
            initSidebar();
        }
    }
    
    // Ejecutar inmediatamente
    startInit();
    
    // También ejecutar después de un pequeño delay para asegurar que todo esté cargado
    setTimeout(function() {
        const checkSidebar = document.querySelector('.sidebar');
        if (checkSidebar && !checkSidebar.hasAttribute('data-initialized')) {
            initSidebar();
        }
    }, 100);
})();

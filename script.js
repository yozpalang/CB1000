document.addEventListener('DOMContentLoaded', function() {
    
    const categories = document.querySelectorAll('.category-section');
    categories.forEach(category => {
        const title = category.querySelector('.category-title');
        const gridContainer = category.querySelector('.grid-container');
        if (title && gridContainer) {
            title.addEventListener('click', () => {
                if (category.classList.contains('active')) {
                    category.classList.remove('active');
                    gridContainer.style.maxHeight = '0px';
                } else {
                    category.classList.add('active');
                    gridContainer.style.maxHeight = gridContainer.scrollHeight + 'px';
                }
            });
        }
    });


    document.addEventListener('click', function(e) {
        const button = e.target.closest('.copy-btn');
        if (button) {
            const url = button.dataset.url;
            navigator.clipboard.writeText(url).then(() => {

                showToast("لینک با موفقیت کپی شد!");

                const copyIcon = button.querySelector('.copy-icon');
                const checkIcon = button.querySelector('.check-icon');
                if (copyIcon && checkIcon) {
                    copyIcon.style.opacity = '0';
                    checkIcon.style.display = 'block';
                    setTimeout(() => { checkIcon.style.opacity = '1'; }, 50);
                    setTimeout(() => {
                        checkIcon.style.opacity = '0';
                        setTimeout(() => {
                            checkIcon.style.display = 'none';
                            copyIcon.style.opacity = '1';
                        }, 300);
                    }, 2000);
                }
            }).catch(err => {
                console.error('Failed to copy URL: ', err);
                showToast("خطا در کپی کردن لینک!");
            });
        }
    });


    function showToast(message) {
        const toast = document.getElementById("toast");
        toast.textContent = message;
        toast.className = "show";
        setTimeout(function(){ 
            toast.className = toast.className.replace("show", ""); 
        }, 3000); // بعد از 3 ثانیه پیغام محو می‌شود
    }
});

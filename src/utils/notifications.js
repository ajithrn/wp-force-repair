
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

/**
 * Show a success toast notification.
 * @param {string} title - The message to display.
 */
export const showSuccessToast = ( title ) => {
    MySwal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        icon: 'success',
        title: title,
        didOpen: (toast) => {
            toast.style.marginTop = '45px';
            const titleEl = toast.querySelector('.swal2-title');
            if (titleEl) {
                titleEl.style.setProperty('font-size', '13px', 'important');
                titleEl.style.setProperty('line-height', '1.4', 'important');
            }
            const icon = toast.querySelector('.swal2-icon');
            if (icon) {
                icon.style.transform = 'scale(0.7)';
                icon.style.margin = '0 10px 0 0';
            }
        }
    });
};

/**
 * Show an error alert.
 * @param {string} title - The title of the error.
 * @param {string} text - The error message.
 */
export const showErrorAlert = ( title, text ) => {
    MySwal.fire( title, text, 'error' );
};

export const showConfirmDialog = async ( title, text, confirmText, icon = 'warning' ) => {
    return MySwal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#d63638',
        confirmButtonText: confirmText
    });
};

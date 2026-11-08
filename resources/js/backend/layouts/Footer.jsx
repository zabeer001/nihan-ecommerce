import React from 'react';

function Footer({ role }) {
    return (
        <footer className="footer footer-center border-t border-base-300 bg-base-100 px-6 py-4 text-sm text-base-content/70">
            <p>Services Admin</p>
            <p>Role: {role.toUpperCase()}</p>
        </footer>
    );
}

export default Footer;

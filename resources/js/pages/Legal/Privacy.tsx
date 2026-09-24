import GuestLayout from '@/layouts/GuestLayout';

export default function Privacy() {
    return (
        <GuestLayout title="Privacy Policy" subtitle="Xgenious Accounting">
            <div className="space-y-4 text-sm text-gray-600 leading-relaxed max-h-[60vh] overflow-y-auto pr-2">
                <p>We collect the minimum information needed to run your accounting workspace.</p>
                <h2 className="text-sm font-semibold text-gray-800">What we store</h2>
                <p>Account details (name, email), your business profile, and the financial records you create (customers, invoices, payments, expenses, ledger entries). Uploaded files are stored securely outside the public web root.</p>
                <h2 className="text-sm font-semibold text-gray-800">How we use it</h2>
                <p>To provide the service, send the emails you request (invoices, reminders, statements), and keep an audit trail of changes for accuracy.</p>
                <h2 className="text-sm font-semibold text-gray-800">Cookies</h2>
                <p>We use a session cookie for authentication and a CSRF token for security. We do not use advertising cookies.</p>
                <h2 className="text-sm font-semibold text-gray-800">Your rights</h2>
                <p>You can export your data from Business settings and delete your account from your profile. Deleting an account does not delete your business&apos;s records unless you are removing the workspace.</p>
                <h2 className="text-sm font-semibold text-gray-800">Contact</h2>
                <p>For privacy requests, contact the platform administrator.</p>
            </div>
        </GuestLayout>
    );
}

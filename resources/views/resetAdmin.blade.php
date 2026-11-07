<style>
.reset-container {
    background: #fff;
    padding: 2.2rem 2rem;
    border-radius: 18px;
    box-shadow: 0 8px 32px rgba(60, 72, 98, 0.13);
    min-width: 320px;
    max-width: 400px;
    width: 100%;
    margin: 40px auto 0 auto;
    text-align: center;
}
.reset-container h2 {
    color: #4f46e5;
    margin-bottom: 1.1rem;
}
.reset-btn {
    width: 100%;
    background: linear-gradient(90deg, #fb923c 0%, #fbbf24 100%);
    color: #fff;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 1.09rem;
    font-weight: bold;
    letter-spacing: 0.04em;
    cursor: pointer;
    margin-top: 8px;
    box-shadow: 0 3px 14px rgba(251,146,60,0.09);
    transition: background 0.18s, box-shadow 0.18s;
}
.reset-btn:hover {
    background: linear-gradient(90deg, #fbbf24 0%, #fb923c 100%);
    box-shadow: 0 6px 24px rgba(251,146,60,0.15);
}
@media (max-width: 500px) {
    .reset-container {
        min-width: 95vw;
        padding: 1.2rem 0.6rem;
    }
}
</style>
<div class="reset-container">
    <h2>Réinitialiser le mot de passe</h2>
    <p>Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet administrateur&nbsp;?</p>
    <form id="reset-form" method="POST" action="{{ route('admin.reset', $admin->id) }}">
        @csrf
        <button class="reset-btn" type="button" onclick="document.getElementById('reset-confirm-modal').style.display='flex'">
            Réinitialiser le compte
        </button>
    </form>

    <!-- Confirmation Modal -->
    <div id="reset-confirm-modal" style="display:none; position:fixed; inset:0; width:100vw; height:100vh; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:18px; padding:24px; max-width:380px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); text-align:center;">
            <div style="width:52px; height:52px; background:#fff7ed; color:#ea580c; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px auto; font-size:24px; border:1px solid #ffedd5;">
                ⚠️
            </div>
            <h3 style="font-size:17px; font-weight:700; color:#0f172a; margin:0 0 8px 0;">Réinitialiser le compte ?</h3>
            <p style="font-size:13px; color:#64748b; line-height:1.5; margin:0 0 20px 0;">
                Confirmez-vous la réinitialisation des accès pour cet administrateur ?
            </p>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="document.getElementById('reset-confirm-modal').style.display='none'" style="flex:1; padding:10px; border-radius:10px; border:1px solid #e2e8f0; background:#f8fafc; color:#475569; font-weight:600; cursor:pointer;">
                    Annuler
                </button>
                <button type="button" onclick="document.getElementById('reset-form').submit()" style="flex:1; padding:10px; border-radius:10px; border:none; background:#ea580c; color:#fff; font-weight:700; cursor:pointer;">
                    Confirmer
                </button>
            </div>
        </div>
    </div>
    @if(session('success'))
        <div style="color:green; margin-top:15px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="color:#ef4444; margin-top:12px;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
</div>

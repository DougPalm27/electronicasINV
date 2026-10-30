package com.honducafe.cuartocontrol;

// Cuarto de control para la tablet (Android). La pantalla es assets/index.html dentro de un WebView; esta clase
// le da lo que una página no puede hacer sola:
//  - revisar cada máquina directo (puerto VNC abierto y estado del candado en el puerto 8765), en segundo plano;
//  - abrir la app VNC de la tablet (AVNC u otra que acepte enlaces vnc://);
//  - guardar en la tablet las IPs que se cambian a mano.
// No pasa por la PC del cuarto de control.

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;
import android.view.WindowManager;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MainActivity extends Activity {
    static final String AVNC = "com.gaurav.avnc";   // app VNC libre y gratis de la Play Store

    WebView web;
    SharedPreferences prefs;
    final ExecutorService hilos = Executors.newFixedThreadPool(12);

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        int fondo = Color.rgb(20, 24, 29);
        getWindow().setStatusBarColor(fondo);
        getWindow().setNavigationBarColor(fondo);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);   // pantalla de monitoreo: no se apaga
        prefs = getSharedPreferences("cuarto", MODE_PRIVATE);

        web = new WebView(this);
        web.setBackgroundColor(fondo);
        web.setOverScrollMode(View.OVER_SCROLL_NEVER);
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setAllowFileAccess(true);           // solo para leer los archivos de la propia app (assets)
        s.setMediaPlaybackRequiresUserGesture(true);
        web.addJavascriptInterface(new Puente(), "AndroidApp");
        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView v, String url) {
                if (url.startsWith("file:///android_asset/")) return false;
                try { startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url))); } catch (Exception e) { }
                return true;   // nada de internet dentro de la app
            }
        });
        web.setWebChromeClient(new WebChromeClient());
        setContentView(web);
        web.loadUrl("file:///android_asset/index.html");
    }

    @Override
    public void onBackPressed() {
        web.evaluateJavascript("window.atras ? window.atras() : false", new ValueCallback<String>() {
            @Override
            public void onReceiveValue(String v) {
                if (!"true".equals(v)) MainActivity.super.onBackPressed();
            }
        });
    }

    @Override
    protected void onDestroy() {
        hilos.shutdownNow();
        super.onDestroy();
    }

    void js(final String codigo) {
        runOnUiThread(new Runnable() {
            @Override
            public void run() { if (web != null) web.evaluateJavascript(codigo, null); }
        });
    }

    void aviso(final String t) {
        runOnUiThread(new Runnable() {
            @Override
            public void run() { Toast.makeText(MainActivity.this, t, Toast.LENGTH_LONG).show(); }
        });
    }

    static boolean puertoAbierto(String host, int puerto, int ms) {
        try (Socket s = new Socket()) {
            s.connect(new InetSocketAddress(host, puerto), ms);
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    static String leer(String url, int ms) {
        HttpURLConnection c = null;
        try {
            c = (HttpURLConnection) new URL(url).openConnection();
            c.setConnectTimeout(ms);
            c.setReadTimeout(ms);
            c.setUseCaches(false);
            if (c.getResponseCode() != 200) return null;
            try (InputStream in = c.getInputStream(); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
                byte[] buf = new byte[4096];
                int n;
                while ((n = in.read(buf)) > 0 && out.size() < 65536) out.write(buf, 0, n);
                return out.toString("UTF-8");
            }
        } catch (Exception e) {
            return null;
        } finally {
            if (c != null) c.disconnect();
        }
    }

    static boolean hostValido(String h) {
        return h != null && h.matches("[A-Za-z0-9][A-Za-z0-9.\\-]{0,99}");
    }

    // Lo que la página puede pedir (objeto AndroidApp en JavaScript)
    class Puente {
        // Revisa una máquina en segundo plano y contesta en window.resultado({id, vnc, estado})
        @JavascriptInterface
        public void revisar(final String id, final String host, final int puertoVnc, final int puertoEstado) {
            if (!hostValido(host)) return;
            hilos.execute(new Runnable() {
                @Override
                public void run() {
                    boolean vnc = puertoAbierto(host, puertoVnc, 1500);
                    String estado = leer("http://" + host + ":" + puertoEstado + "/estado", 2500);
                    try {
                        JSONObject r = new JSONObject();
                        r.put("id", id);
                        r.put("vnc", vnc);
                        r.put("estado", estado == null ? JSONObject.NULL : new JSONObject(estado));
                        js("window.resultado && window.resultado(" + r.toString() + ")");
                    } catch (Exception e) {
                        js("window.resultado && window.resultado({\"id\":" + JSONObject.quote(id) + ",\"vnc\":" + vnc + ",\"estado\":null})");
                    }
                }
            });
        }

        // Abre la app VNC de la tablet. La contraseña de VNC la pide esa app (aquí no se guarda).
        @JavascriptInterface
        public void abrirVnc(String host, int puerto) {
            if (!hostValido(host)) return;
            Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse("vnc://" + host + ":" + puerto));
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            try {
                startActivity(i);
            } catch (ActivityNotFoundException e) {
                copiar(host + ":" + puerto);
                aviso("No hay una app VNC en la tablet. Instala AVNC desde la Play Store. La dirección quedó copiada.");
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse("market://details?id=" + AVNC)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
                } catch (Exception e2) { }
            }
        }

        @JavascriptInterface
        public void guardar(String clave, String valor) {
            prefs.edit().putString(clave, valor).apply();
        }

        @JavascriptInterface
        public String cargar(String clave) {
            return prefs.getString(clave, null);
        }

        @JavascriptInterface
        public void copiar(final String t) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    ClipboardManager cm = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
                    if (cm != null) cm.setPrimaryClip(ClipData.newPlainText("IP", t));
                }
            });
        }
    }
}

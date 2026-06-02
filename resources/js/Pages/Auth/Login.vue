<script setup>
import { ref } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

const isDark = ref(localStorage.getItem('theme') !== 'light')
function toggleTheme() {
    isDark.value = !isDark.value
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
}

function switchLang(l) {
    locale.value = l
    localStorage.setItem('wbn_locale', l)
}

const form    = useForm({ employee_id: '', password: '', remember: false })
const showPw  = ref(false)
const shaking = ref(false)

function submit() {
    form.post(route('login'), {
        onError:  () => {
            shaking.value = false
            void document.getElementById('wms-card')?.offsetWidth
            shaking.value = true
        },
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign In" />
    <div class="wms-login" :data-theme="isDark ? 'dark' : 'light'" :lang="locale==='zh'?'zh-CN':locale">

        <div class="sky-wash"></div>

        <!-- scene -->
        <div class="scene">
          <svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
              <linearGradient id="skyOut" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1e2942"/><stop offset="60%" stop-color="#3b4a6b"/><stop offset="100%" stop-color="#7d6a52"/></linearGradient>
              <linearGradient id="floorGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1a2236"/><stop offset="100%" stop-color="#0a0f1c"/></linearGradient>
              <linearGradient id="wallGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1a2440"/><stop offset="100%" stop-color="#0e1628"/></linearGradient>
              <linearGradient id="rackGrad" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#0c1322"/><stop offset="50%" stop-color="#1a2440"/><stop offset="100%" stop-color="#0c1322"/></linearGradient>
              <linearGradient id="cratGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#b88247"/><stop offset="100%" stop-color="#7a4f24"/></linearGradient>
              <linearGradient id="cratGrad2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3c4a66"/><stop offset="100%" stop-color="#222a3e"/></linearGradient>
              <linearGradient id="vest" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fb923c"/><stop offset="100%" stop-color="#ea580c"/></linearGradient>
              <radialGradient id="lampGlow" cx="0.5" cy="0.5" r="0.5"><stop offset="0%" stop-color="#fde68a" stop-opacity="0.8"/><stop offset="60%" stop-color="#f59e0b" stop-opacity="0.2"/><stop offset="100%" stop-color="#f59e0b" stop-opacity="0"/></radialGradient>
              <pattern id="floorTile" x="0" y="0" width="80" height="80" patternUnits="userSpaceOnUse"><rect width="80" height="80" fill="url(#floorGrad)"/><path d="M0 0 L80 0 M0 80 L80 80 M0 0 L0 80 M80 0 L80 80" stroke="rgba(148,184,255,.04)" stroke-width="1"/></pattern>
              <clipPath id="windowClip"><rect x="386" y="186" width="628" height="348"/></clipPath>
            </defs>

            <!-- wall -->
            <rect class="scene-fill-wall" x="0" y="0" width="1600" height="600" fill="url(#wallGrad)"/>

            <!-- roof -->
            <g opacity="0.6">
              <polygon class="scene-fill-roof" points="0,0 1600,0 1500,80 100,80" fill="#0a0f1c"/>
              <line x1="100" y1="80" x2="1500" y2="80" stroke="rgba(148,184,255,.18)" stroke-width="2"/>
              <g stroke="rgba(148,184,255,.08)" stroke-width="1.5">
                <line x1="200" y1="80" x2="200" y2="0"/><line x1="400" y1="80" x2="400" y2="0"/>
                <line x1="600" y1="80" x2="600" y2="0"/><line x1="800" y1="80" x2="800" y2="0"/>
                <line x1="1000" y1="80" x2="1000" y2="0"/><line x1="1200" y1="80" x2="1200" y2="0"/>
                <line x1="1400" y1="80" x2="1400" y2="0"/>
              </g>
              <path d="M100,80 L300,20 L500,80 L700,20 L900,80 L1100,20 L1300,80 L1500,20" stroke="rgba(148,184,255,.06)" stroke-width="1.2" fill="none"/>
            </g>

            <!-- lamps -->
            <g>
              <g transform="translate(220,80)"><line x1="0" y1="0" x2="0" y2="60" stroke="#222a3e" stroke-width="2"/><ellipse cx="0" cy="68" rx="22" ry="8" fill="#222a3e"/><ellipse cx="0" cy="74" rx="14" ry="5" fill="#fde68a"/><circle cx="0" cy="74" r="80" fill="url(#lampGlow)" opacity=".45"/></g>
              <g transform="translate(560,80)"><line x1="0" y1="0" x2="0" y2="60" stroke="#222a3e" stroke-width="2"/><ellipse cx="0" cy="68" rx="22" ry="8" fill="#222a3e"/><ellipse cx="0" cy="74" rx="14" ry="5" fill="#fde68a"/><circle cx="0" cy="74" r="100" fill="url(#lampGlow)" opacity=".5"/></g>
              <g transform="translate(980,80)"><line x1="0" y1="0" x2="0" y2="60" stroke="#222a3e" stroke-width="2"/><ellipse cx="0" cy="68" rx="22" ry="8" fill="#222a3e"/><ellipse cx="0" cy="74" rx="14" ry="5" fill="#fde68a"/><circle cx="0" cy="74" r="90" fill="url(#lampGlow)" opacity=".45"/></g>
              <g transform="translate(1320,80)"><line x1="0" y1="0" x2="0" y2="60" stroke="#222a3e" stroke-width="2"/><ellipse cx="0" cy="68" rx="22" ry="8" fill="#222a3e"/><ellipse cx="0" cy="74" rx="14" ry="5" fill="#fde68a"/><circle cx="0" cy="74" r="80" fill="url(#lampGlow)" opacity=".4"/></g>
            </g>

            <!-- bay window -->
            <g>
              <rect x="380" y="180" width="640" height="360" fill="#0a0f1c" stroke="#222a3e" stroke-width="6"/>
              <g clip-path="url(#windowClip)">
                <rect class="scene-fill-window" x="386" y="186" width="628" height="200" fill="url(#skyOut)"/>
                <path d="M386,360 L500,300 L580,330 L700,260 L820,310 L920,280 L1014,330 L1014,386 L386,386 Z" fill="#5a4a35" opacity=".9"/>
                <path d="M386,386 L460,360 L560,380 L660,355 L780,380 L880,360 L1014,385 L1014,420 L386,420 Z" fill="#7a6244" opacity=".95"/>
                <path d="M386,420 L1014,420 L1014,440 L386,440 Z" fill="#8a6e4d"/>
                <path d="M386,440 L1014,440 L1014,460 L386,460 Z" fill="#6b5238"/>
                <path d="M386,460 L1014,460 L1014,480 L386,480 Z" fill="#544027"/>
                <path d="M386,480 L1014,480 L1014,510 L386,510 Z" fill="#3d2e1c"/>
                <path d="M386,510 L1014,510 L1014,540 L386,540 Z" fill="#2a2014"/>
                <g transform="translate(540,400)">
                  <rect x="-22" y="-14" width="44" height="14" fill="#1f2937"/>
                  <rect x="-26" y="0" width="52" height="6" fill="#0f1422"/>
                  <circle cx="-18" cy="6" r="5" fill="#0a0f1a"/><circle cx="0" cy="6" r="5" fill="#0a0f1a"/><circle cx="18" cy="6" r="5" fill="#0a0f1a"/>
                  <rect x="-10" y="-26" width="22" height="14" fill="#f59e0b"/>
                  <g class="excavator-arm">
                    <line x1="6" y1="-20" x2="46" y2="-40" stroke="#f59e0b" stroke-width="6"/>
                    <line x1="46" y1="-40" x2="60" y2="-22" stroke="#f59e0b" stroke-width="5"/>
                    <path d="M55,-20 L70,-12 L66,-2 L52,-10 Z" fill="#1f2937"/>
                  </g>
                </g>
                <g class="truck-out" transform="translate(720,440)"><rect x="0" y="-10" width="36" height="14" fill="#f97316"/><rect x="-6" y="-6" width="14" height="10" fill="#1f2937"/><circle cx="2" cy="6" r="4" fill="#0a0f1a"/><circle cx="14" cy="6" r="4" fill="#0a0f1a"/><circle cx="26" cy="6" r="4" fill="#0a0f1a"/></g>
                <g class="truck-out" style="animation-delay:-12s" transform="translate(420,475)"><rect x="0" y="-8" width="28" height="11" fill="#1e3a8a"/><rect x="-4" y="-5" width="10" height="8" fill="#0f1422"/><circle cx="2" cy="5" r="3" fill="#0a0f1a"/><circle cx="22" cy="5" r="3" fill="#0a0f1a"/></g>
                <g transform="translate(880,340)" opacity=".85"><polygon points="0,0 -16,60 16,60" fill="#0f1422"/><line x1="0" y1="0" x2="0" y2="60" stroke="#222a3e"/><line x1="-12" y1="50" x2="12" y2="50" stroke="#222a3e"/><line x1="-8" y1="35" x2="8" y2="35" stroke="#222a3e"/><circle cx="0" cy="-2" r="3" fill="#f97316"/></g>
                <rect x="386" y="380" width="628" height="160" fill="#7a5a3a" opacity=".25"/>
              </g>
              <line x1="595" y1="180" x2="595" y2="540" stroke="#0a0f1c" stroke-width="3"/>
              <line x1="800" y1="180" x2="800" y2="540" stroke="#0a0f1c" stroke-width="3"/>
              <line x1="380" y1="360" x2="1020" y2="360" stroke="#0a0f1c" stroke-width="3"/>
              <rect x="386" y="186" width="628" height="348" fill="url(#lampGlow)" opacity=".06"/>
            </g>

            <!-- conveyor -->
            <g transform="translate(1080,420)">
              <line x1="20" y1="40" x2="20" y2="100" stroke="#1a2236" stroke-width="6"/><line x1="180" y1="40" x2="180" y2="100" stroke="#1a2236" stroke-width="6"/><line x1="340" y1="40" x2="340" y2="100" stroke="#1a2236" stroke-width="6"/><line x1="500" y1="40" x2="500" y2="100" stroke="#1a2236" stroke-width="6"/>
              <rect x="0" y="20" width="520" height="22" fill="#0c1322" stroke="#222a3e"/>
              <rect x="2" y="22" width="516" height="18" fill="#1a2236"/>
              <g class="conveyor-belt-stripe" fill="rgba(148,184,255,.18)">
                <rect x="2" y="22" width="14" height="18"/><rect x="34" y="22" width="14" height="18"/><rect x="66" y="22" width="14" height="18"/><rect x="98" y="22" width="14" height="18"/><rect x="130" y="22" width="14" height="18"/><rect x="162" y="22" width="14" height="18"/><rect x="194" y="22" width="14" height="18"/><rect x="226" y="22" width="14" height="18"/><rect x="258" y="22" width="14" height="18"/><rect x="290" y="22" width="14" height="18"/><rect x="322" y="22" width="14" height="18"/><rect x="354" y="22" width="14" height="18"/><rect x="386" y="22" width="14" height="18"/><rect x="418" y="22" width="14" height="18"/><rect x="450" y="22" width="14" height="18"/><rect x="482" y="22" width="14" height="18"/><rect x="514" y="22" width="14" height="18"/>
              </g>
              <rect x="80" y="0" width="40" height="22" fill="url(#cratGrad)"/><rect x="80" y="0" width="40" height="22" fill="none" stroke="#3a2410" stroke-width="1"/>
              <rect x="240" y="2" width="36" height="20" fill="url(#cratGrad2)"/>
              <rect x="380" y="0" width="44" height="22" fill="url(#cratGrad)"/><rect x="380" y="0" width="44" height="22" fill="none" stroke="#3a2410" stroke-width="1"/>
            </g>

            <!-- left rack -->
            <g transform="translate(40,200)">
              <rect x="0" y="0" width="10" height="540" fill="url(#rackGrad)" class="scene-fill-rack-dark"/><rect x="290" y="0" width="10" height="540" fill="url(#rackGrad)" class="scene-fill-rack-dark"/>
              <rect x="0" y="0" width="300" height="8" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="170" width="300" height="8" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="340" width="300" height="8" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="532" width="300" height="8" fill="#1a2236" class="scene-fill-rack"/>
              <g transform="translate(20,80)"><rect width="80" height="86" fill="url(#cratGrad)"/><line x1="0" y1="20" x2="80" y2="20" stroke="#3a2410" stroke-width="1"/><line x1="0" y1="60" x2="80" y2="60" stroke="#3a2410" stroke-width="1"/></g>
              <g transform="translate(120,90)"><rect width="70" height="76" fill="url(#cratGrad2)"/><rect x="6" y="10" width="58" height="3" fill="#0f1422"/><rect x="6" y="60" width="58" height="3" fill="#0f1422"/></g>
              <g transform="translate(210,100)"><rect width="60" height="66" fill="url(#cratGrad)"/><rect x="6" y="10" width="48" height="2" fill="#3a2410"/></g>
              <g transform="translate(30,260)"><ellipse cx="30" cy="78" rx="28" ry="6" fill="#0a0f1a"/><rect x="4" y="20" width="52" height="58" fill="#1f2937"/><rect x="4" y="20" width="52" height="6" fill="#374151"/><rect x="4" y="40" width="52" height="2" fill="#0a0f1a"/><rect x="4" y="60" width="52" height="2" fill="#0a0f1a"/><rect x="20" y="32" width="20" height="10" fill="#f97316"/></g>
              <g transform="translate(110,260)"><ellipse cx="30" cy="78" rx="28" ry="6" fill="#0a0f1a"/><rect x="4" y="20" width="52" height="58" fill="#1f2937"/><rect x="4" y="20" width="52" height="6" fill="#374151"/><rect x="4" y="40" width="52" height="2" fill="#0a0f1a"/><rect x="20" y="32" width="20" height="10" fill="#f59e0b"/></g>
              <g transform="translate(190,265)"><rect x="0" y="20" width="90" height="58" fill="url(#cratGrad2)"/><rect x="0" y="20" width="90" height="6" fill="#3c4a66"/></g>
              <g transform="translate(20,440)"><rect width="120" height="92" fill="url(#cratGrad)"/><line x1="0" y1="22" x2="120" y2="22" stroke="#3a2410" stroke-width="1"/><line x1="60" y1="0" x2="60" y2="92" stroke="#3a2410" stroke-width="1"/></g>
              <g transform="translate(160,450)"><rect width="60" height="82" fill="url(#cratGrad2)"/></g>
              <g transform="translate(230,460)"><rect width="50" height="72" fill="url(#cratGrad)"/></g>
              <g font-family="JetBrains Mono, monospace" font-size="9" fill="#f59e0b" opacity=".85"><text x="20" y="74">A-01</text><text x="20" y="244">B-12</text><text x="20" y="416">C-07</text></g>
            </g>

            <!-- right rack -->
            <g transform="translate(1320,260)" opacity=".75">
              <rect x="0" y="0" width="8" height="440" fill="#0c1322" class="scene-fill-rack-dark"/><rect x="220" y="0" width="8" height="440" fill="#0c1322" class="scene-fill-rack-dark"/>
              <rect x="0" y="0" width="228" height="6" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="140" width="228" height="6" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="280" width="228" height="6" fill="#1a2236" class="scene-fill-rack"/><rect x="0" y="432" width="228" height="6" fill="#1a2236" class="scene-fill-rack"/>
              <g transform="translate(14,60)"><rect width="60" height="78" fill="url(#cratGrad)"/></g>
              <g transform="translate(86,70)"><rect width="60" height="68" fill="url(#cratGrad2)"/></g>
              <g transform="translate(158,75)"><rect width="56" height="62" fill="url(#cratGrad)"/></g>
              <g transform="translate(14,200)"><rect width="200" height="76" fill="url(#cratGrad2)"/></g>
              <g transform="translate(14,348)"><rect width="120" height="82" fill="url(#cratGrad)"/></g>
              <g transform="translate(146,360)"><rect width="68" height="70" fill="url(#cratGrad2)"/></g>
            </g>

            <!-- floor -->
            <rect class="scene-fill-floor" x="0" y="700" width="1600" height="300" fill="url(#floorTile)"/>
            <rect class="scene-fill-floor-2" x="0" y="700" width="1600" height="80" fill="rgba(148,184,255,.04)"/>
            <g stroke="#f59e0b" stroke-width="3" opacity=".55"><line x1="0" y1="780" x2="1600" y2="780" stroke-dasharray="24 16"/></g>
            <g stroke="#f97316" stroke-width="2" opacity=".4"><line x1="0" y1="900" x2="1600" y2="900" stroke-dasharray="40 30"/></g>

            <!-- pallets -->
            <g transform="translate(420,810)"><rect width="120" height="14" y="50" fill="#7a4f24"/><rect width="120" height="6" y="70" fill="#5a3a1a"/><rect width="6" height="22" x="4" y="46" fill="#5a3a1a"/><rect width="6" height="22" x="56" y="46" fill="#5a3a1a"/><rect width="6" height="22" x="110" y="46" fill="#5a3a1a"/><rect width="100" height="46" x="10" y="0" fill="url(#cratGrad)"/></g>
            <g transform="translate(900,830)"><rect width="130" height="14" y="40" fill="#7a4f24"/><rect width="130" height="6" y="60" fill="#5a3a1a"/><rect width="6" height="22" x="4" y="36" fill="#5a3a1a"/><rect width="6" height="22" x="62" y="36" fill="#5a3a1a"/><rect width="6" height="22" x="120" y="36" fill="#5a3a1a"/><rect width="110" height="36" x="10" y="0" fill="url(#cratGrad2)"/></g>

            <!-- Worker A -->
            <g class="worker worker-walk-a" transform="translate(360,860)">
              <ellipse cx="0" cy="56" rx="13" ry="3" fill="rgba(0,0,0,.45)"/>
              <g class="worker-bob">
                <g class="leg-l"><rect x="-5" y="32" width="5" height="22" rx="1" fill="#1f2937"/></g>
                <g class="leg-r"><rect x="0" y="32" width="5" height="22" rx="1" fill="#1f2937"/></g>
                <rect x="-9" y="14" width="18" height="20" rx="2" fill="url(#vest)"/>
                <rect x="-9" y="22" width="18" height="2" fill="#fff" opacity=".75"/><rect x="-9" y="30" width="18" height="2" fill="#fff" opacity=".55"/>
                <rect x="6" y="18" width="4" height="14" rx="1" fill="#1f2937"/>
                <rect x="9" y="22" width="9" height="11" rx="1" fill="#fde68a"/>
                <line x1="11" y1="25" x2="16" y2="25" stroke="#1f2937" stroke-width="1"/><line x1="11" y1="28" x2="16" y2="28" stroke="#1f2937" stroke-width="1"/>
                <circle cx="0" cy="6" r="6" fill="#c8a079"/>
                <path d="M-7,4 Q0,-5 7,4 Z" fill="#fbbf24"/><rect x="-8" y="2" width="16" height="2" fill="#fbbf24"/>
              </g>
            </g>

            <!-- Worker B -->
            <g class="worker" transform="translate(560,858)">
              <ellipse cx="0" cy="56" rx="14" ry="3" fill="rgba(0,0,0,.45)"/>
              <g class="lift-torso">
                <rect x="-5" y="32" width="5" height="22" rx="1" fill="#1f2937"/><rect x="0" y="32" width="5" height="22" rx="1" fill="#1f2937"/>
                <rect x="-9" y="14" width="18" height="20" rx="2" fill="url(#vest)"/>
                <rect x="-9" y="22" width="18" height="2" fill="#fff" opacity=".75"/><rect x="-9" y="30" width="18" height="2" fill="#fff" opacity=".55"/>
                <rect x="-12" y="18" width="4" height="14" rx="1" fill="#1f2937" transform="rotate(-25 -10 18)"/>
                <g class="lift-arm"><rect x="6" y="14" width="4" height="16" rx="1" fill="#1f2937"/><rect x="2" y="6" width="14" height="10" fill="url(#cratGrad)"/><line x1="2" y1="11" x2="16" y2="11" stroke="#3a2410" stroke-width="1"/></g>
                <circle cx="0" cy="6" r="6" fill="#c8a079"/>
                <path d="M-7,4 Q0,-5 7,4 Z" fill="#fbbf24"/><rect x="-8" y="2" width="16" height="2" fill="#fbbf24"/>
              </g>
            </g>

            <!-- Worker C -->
            <g class="worker" transform="translate(1060,856)">
              <ellipse cx="0" cy="56" rx="13" ry="3" fill="rgba(0,0,0,.45)"/>
              <g>
                <rect x="-5" y="32" width="5" height="22" rx="1" fill="#1f2937"/><rect x="0" y="32" width="5" height="22" rx="1" fill="#1f2937"/>
                <rect x="-9" y="14" width="18" height="20" rx="2" fill="#3b82f6"/>
                <rect x="-9" y="22" width="18" height="2" fill="#fff" opacity=".7"/>
                <rect x="-11" y="20" width="4" height="12" rx="1" fill="#1f2937"/>
                <g class="stack-arm"><rect x="6" y="14" width="4" height="16" rx="1" fill="#1f2937"/><rect x="3" y="2" width="12" height="10" fill="url(#cratGrad2)"/></g>
                <circle cx="0" cy="6" r="6" fill="#c8a079"/>
                <path d="M-7,4 Q0,-5 7,4 Z" fill="#fbbf24"/><rect x="-8" y="2" width="16" height="2" fill="#fbbf24"/>
              </g>
              <g transform="translate(20,18)"><rect width="34" height="14" y="22" fill="url(#cratGrad)"/><rect width="34" height="14" y="36" fill="url(#cratGrad2)"/><line x1="0" y1="29" x2="34" y2="29" stroke="#3a2410" stroke-width=".5"/></g>
            </g>

            <!-- Worker D -->
            <g class="worker worker-walk-b" transform="translate(1240,852)">
              <g transform="scale(.85)">
                <ellipse cx="0" cy="56" rx="13" ry="3" fill="rgba(0,0,0,.45)"/>
                <g class="worker-bob">
                  <g class="leg-l"><rect x="-5" y="32" width="5" height="22" rx="1" fill="#1f2937"/></g>
                  <g class="leg-r"><rect x="0" y="32" width="5" height="22" rx="1" fill="#1f2937"/></g>
                  <rect x="-9" y="14" width="18" height="20" rx="2" fill="url(#vest)"/>
                  <rect x="-9" y="22" width="18" height="2" fill="#fff" opacity=".75"/>
                  <rect x="-12" y="20" width="4" height="10" rx="1" fill="#1f2937"/><rect x="6" y="20" width="4" height="10" rx="1" fill="#1f2937"/>
                  <rect x="-10" y="10" width="20" height="14" fill="url(#cratGrad)"/>
                  <line x1="-10" y1="17" x2="10" y2="17" stroke="#3a2410" stroke-width=".5"/>
                  <circle cx="0" cy="6" r="6" fill="#c8a079"/>
                  <path d="M-7,4 Q0,-5 7,4 Z" fill="#fbbf24"/><rect x="-8" y="2" width="16" height="2" fill="#fbbf24"/>
                </g>
              </g>
            </g>

            <!-- pylons -->
            <g transform="translate(700,920)"><polygon points="0,0 -10,30 10,30" fill="#f97316"/><rect x="-7" y="10" width="14" height="3" fill="#fff"/><rect x="-12" y="30" width="24" height="4" fill="#1a2236"/></g>
            <g transform="translate(1180,930)"><polygon points="0,0 -10,30 10,30" fill="#f97316"/><rect x="-7" y="10" width="14" height="3" fill="#fff"/><rect x="-12" y="30" width="24" height="4" fill="#1a2236"/></g>
          </svg>

          <!-- beam cones -->
          <div class="beams" aria-hidden="true">
            <div class="beam b1"></div><div class="beam b2"></div><div class="beam b3"></div>
          </div>

          <!-- forklift -->
          <div class="forklift-track" aria-hidden="true">
            <svg viewBox="0 0 260 160" width="260" height="160">
              <defs>
                <linearGradient id="fbody" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient>
              </defs>
              <g class="forklift-bob">
                <ellipse cx="120" cy="150" rx="100" ry="6" fill="rgba(0,0,0,.35)"/>
                <rect x="40" y="20" width="8" height="110" fill="#374151"/><rect x="56" y="20" width="8" height="110" fill="#374151"/>
                <rect x="0" y="100" width="60" height="6" fill="#9ca3af"/><rect x="0" y="120" width="60" height="6" fill="#9ca3af"/>
                <rect x="0" y="60" width="58" height="44" fill="url(#cratGrad)"/><rect x="0" y="60" width="58" height="44" fill="none" stroke="#3a2410"/>
                <line x1="0" y1="78" x2="58" y2="78" stroke="#3a2410"/><line x1="29" y1="60" x2="29" y2="104" stroke="#3a2410"/>
                <path d="M70,60 L210,60 L220,80 L220,118 L70,118 Z" fill="url(#fbody)"/>
                <path d="M70,60 L210,60 L220,80 L70,80 Z" fill="rgba(0,0,0,.18)"/>
                <rect x="120" y="20" width="80" height="46" fill="#1f2937"/>
                <rect x="128" y="28" width="64" height="32" fill="#60a5fa" opacity=".55"/>
                <line x1="160" y1="28" x2="160" y2="60" stroke="#1f2937" stroke-width="2"/>
                <circle cx="148" cy="42" r="6" fill="#1f2937"/><rect x="142" y="48" width="14" height="14" fill="#1f2937"/>
                <circle cx="74" cy="92" r="5" fill="#fde68a"/><circle cx="74" cy="92" r="14" fill="#fde68a" opacity=".25"/>
                <g transform="translate(102,128)"><circle class="wheel" r="14" fill="#0a0f1a"/><circle r="6" fill="#374151"/></g>
                <g transform="translate(190,128)"><circle class="wheel" r="14" fill="#0a0f1a"/><circle r="6" fill="#374151"/></g>
                <circle cx="160" cy="14" r="4" fill="#f97316"><animate attributeName="opacity" values="1;.2;1" dur="0.9s" repeatCount="indefinite"/></circle>
              </g>
            </svg>
          </div>

          <div class="dust"></div>
          <div class="vignette"></div>
        </div><!-- /scene -->

        <!-- corner tag -->
        <div class="sys-tag" aria-hidden="true">
          <span class="pip"></span>
          <span>{{t('auth.terminal') }}</span>
        </div>

        <!-- login card -->
        <div class="login-wrap">
          <main id="wms-card" class="card" :class="{ shake: shaking }" @animationend="shaking=false" role="main">

            <div class="card-top">
              <div class="lang-switch" role="tablist">
                <button v-for="l in ['en','zh','id']" :key="l" type="button"
                  :class="{ active: lang===l }" :aria-selected="lang===l" @click="switchLang(l)">
                  {{ l==='zh' ? '中文' : l.toUpperCase() }}
                </button>
              </div>
              <button class="theme-toggle" type="button" @click="toggleTheme" :aria-label="isDark?'Switch to light':'Switch to dark'">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
              </button>
            </div>

            <div class="brand">
              <div class="brand-logo"><img src="/assets/wbn-logo.png" alt="Weda Bay Nickel" /></div>
              <div class="brand-system">{{t('auth.system') }}</div>
            </div>

            <h1 class="heading">{{t('auth.welcome') }}</h1>
            <p class="sub">{{t('auth.sub') }}</p>

            <form @submit.prevent="submit" novalidate>
              <div>
                <label class="field-label" for="emp-id">{{t('auth.empId') }}</label>
                <div class="field" :class="{ 'field-error': form.errors.employee_id }">
                  <span class="lead-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><circle cx="9" cy="12" r="2.2"/><path d="M14 11h4M14 14h3"/></svg>
                  </span>
                  <input id="emp-id" v-model="form.employee_id" type="text" autocomplete="username" spellcheck="false" :placeholder="t('auth.empIdPh')" required/>
                </div>
              </div>
              <div>
                <label class="field-label" for="pw">{{t('auth.password') }}</label>
                <div class="field" :class="{ 'field-error': form.errors.password }">
                  <span class="lead-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                  </span>
                  <input id="pw" v-model="form.password" :type="showPw?'text':'password'" autocomplete="current-password" placeholder="••••••••••••" required/>
                  <button type="button" class="eye-toggle" @click="showPw=!showPw">
                    <svg v-if="!showPw" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.6 6.1A10 10 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.5 4.3"/><path d="M6.1 6.1A17 17 0 0 0 2 12s3.5 6 10 6c1.7 0 3.2-.4 4.5-1"/><path d="M9.9 9.9A3 3 0 0 0 12 15a3 3 0 0 0 2.1-.9"/></svg>
                  </button>
                </div>
              </div>
              <div class="row">
                <label class="check">
                  <input v-model="form.remember" type="checkbox"/>
                  <span class="box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                  <span>{{t('auth.remember') }}</span>
                </label>
                <a class="link" :href="route('password.request')">{{t('auth.forgot') }}</a>
              </div>
              <button class="btn" type="submit" :disabled="form.processing">
                <span class="label">
                  <span v-if="!form.processing">{{t('auth.signIn') }}</span>
                  <span v-else class="spinner" aria-hidden="true"></span>
                  <svg v-if="!form.processing" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                </span>
              </button>
              <div v-if="form.errors.employee_id||form.errors.password" class="error show" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <span>{{t('auth.error') }}</span>
              </div>
            </form>

            <div class="divider"></div>
            <div class="foot">
              <span>{{t('auth.version') }}</span>
              <span class="status"><span class="dot"></span><span>{{t('auth.online') }}</span></span>
            </div>
          </main>
        </div>
    </div>
</template>

<style>
.wms-login {
    --orange-400:#fb923c; --orange-500:#f97316; --amber-400:#fbbf24; --blue-400:#60a5fa; --blue-700:#1d4ed8; --emerald:#10b981;
    --bg:#0d1117; --fg:#e6edf7; --fg-muted:#8a99b3; --fg-dim:#5b6b87;
    --card-bg:rgba(15,23,42,.55); --card-border:rgba(148,184,255,.18); --card-highlight:rgba(255,255,255,.06);
    --field-bg:rgba(13,17,23,.55); --field-border:rgba(148,184,255,.16); --field-border-focus:#f97316;
    --divider:rgba(148,184,255,.12); --chip-bg:rgba(13,17,23,.55);
    --shadow-card:0 30px 80px -20px rgba(0,0,0,.8),0 8px 24px -8px rgba(0,0,0,.6);
    position:fixed; inset:0; overflow:hidden;
    font-family:'Inter',system-ui,-apple-system,sans-serif; -webkit-font-smoothing:antialiased;
    background:var(--bg); color:var(--fg); transition:background 320ms ease,color 320ms ease;
}
.wms-login[data-theme="light"] {
    --bg:#f8fafc; --fg:#0b1530; --fg-muted:#4b587a; --fg-dim:#8493ae;
    --card-bg:rgba(255,255,255,.72); --card-border:rgba(30,58,138,.12); --card-highlight:rgba(255,255,255,.9);
    --field-bg:rgba(255,255,255,.85); --field-border:rgba(30,58,138,.18);
    --divider:rgba(30,58,138,.12); --chip-bg:rgba(255,255,255,.7);
    --shadow-card:0 30px 80px -25px rgba(15,23,42,.25),0 6px 18px -6px rgba(15,23,42,.12);
}
.wms-login .scene{position:absolute;inset:0;}
.wms-login .scene svg{width:100%;height:100%;display:block;}
.wms-login .sky-wash{
    position:absolute;inset:0;
    background:radial-gradient(120% 80% at 18% 32%,rgba(59,130,246,.18),transparent 60%),radial-gradient(80% 70% at 78% 12%,rgba(249,115,22,.10),transparent 65%),linear-gradient(180deg,#0a0f1c 0%,#0d1117 55%,#060912 100%);
    transition:opacity 320ms ease;
}
.wms-login[data-theme="light"] .sky-wash{background:radial-gradient(120% 80% at 18% 32%,rgba(59,130,246,.20),transparent 60%),radial-gradient(80% 70% at 78% 12%,rgba(249,115,22,.18),transparent 65%),linear-gradient(180deg,#e6eef9 0%,#f1f5fb 55%,#dbe6f2 100%);}
.wms-login .beams{position:absolute;inset:0;pointer-events:none;mix-blend-mode:screen;}
.wms-login[data-theme="light"] .beams{mix-blend-mode:multiply;opacity:.35;}
.wms-login .beam{position:absolute;top:0;width:220px;height:70%;background:linear-gradient(180deg,rgba(251,191,36,.32),rgba(249,115,22,.04) 60%,transparent 80%);clip-path:polygon(45% 0,55% 0,100% 100%,0 100%);filter:blur(4px);animation:wmsFlicker 5s ease-in-out infinite;}
.wms-login .beam.b1{left:8%;animation-delay:-1.2s;}
.wms-login .beam.b2{left:28%;animation-delay:-2.6s;opacity:.85;}
.wms-login .beam.b3{left:48%;animation-delay:-0.4s;opacity:.9;}
@keyframes wmsFlicker{0%,100%{opacity:1}47%{opacity:.92}48%{opacity:.65}49%{opacity:1}72%{opacity:.88}73%{opacity:1}}
.wms-login .dust{position:absolute;inset:0;background-image:radial-gradient(1px 1px at 20% 30%,rgba(255,220,180,.5),transparent 60%),radial-gradient(1px 1px at 60% 50%,rgba(255,220,180,.4),transparent 60%),radial-gradient(1px 1px at 35% 70%,rgba(255,220,180,.45),transparent 60%),radial-gradient(1px 1px at 80% 20%,rgba(255,220,180,.35),transparent 60%),radial-gradient(1px 1px at 15% 80%,rgba(255,220,180,.4),transparent 60%);animation:wmsDustDrift 28s linear infinite;opacity:.5;pointer-events:none;}
@keyframes wmsDustDrift{0%{transform:translate3d(0,0,0)}100%{transform:translate3d(-40px,-20px,0)}}
.wms-login .vignette{position:absolute;inset:0;background:radial-gradient(120% 90% at 50% 60%,transparent 50%,rgba(0,0,0,.55) 100%);pointer-events:none;}
.wms-login[data-theme="light"] .vignette{background:radial-gradient(120% 90% at 50% 60%,transparent 55%,rgba(15,23,42,.18) 100%);}
.wms-login .forklift-track{position:absolute;bottom:17%;left:-260px;width:260px;height:160px;will-change:transform;animation:wmsForklift 28s linear infinite;}
@keyframes wmsForklift{0%{transform:translate3d(0,0,0)}100%{transform:translate3d(calc(100vw + 260px),0,0)}}
.wms-login .forklift-bob{animation:wmsBob 1.6s ease-in-out infinite;transform-origin:center bottom;will-change:transform;}
@keyframes wmsBob{0%,100%{transform:translate3d(0,0,0) rotate(-.25deg)}50%{transform:translate3d(0,-1.5px,0) rotate(.25deg)}}
.wms-login .wheel{transform-origin:center;animation:wmsSpin 1.6s linear infinite;will-change:transform;}
@keyframes wmsSpin{to{transform:rotate(360deg)}}
.wms-login .truck-out{animation:wmsTruckCrawl 38s linear infinite;}
@keyframes wmsTruckCrawl{0%{transform:translateX(0)}100%{transform:translateX(120px)}}
.wms-login .excavator-arm{transform-origin:540px 360px;animation:wmsDigSwing 7.5s cubic-bezier(.42,0,.58,1) infinite;will-change:transform;}
@keyframes wmsDigSwing{0%,100%{transform:rotate(0deg)}25%{transform:rotate(-4deg)}50%{transform:rotate(-7.5deg)}75%{transform:rotate(-3deg)}}
.wms-login .worker{will-change:transform;}
.wms-login .worker-walk-a{animation:wmsWalkRight 18s linear infinite;}
@keyframes wmsWalkRight{0%{transform:translate3d(0,0,0)}100%{transform:translate3d(180px,0,0)}}
.wms-login .worker-walk-b{animation:wmsWalkLeft 22s linear infinite;}
@keyframes wmsWalkLeft{0%{transform:translate3d(0,0,0)}100%{transform:translate3d(-160px,0,0)}}
.wms-login .worker-bob{animation:wmsWorkerBob .55s ease-in-out infinite;transform-origin:center bottom;}
@keyframes wmsWorkerBob{0%,100%{transform:translateY(0) rotate(-.4deg)}50%{transform:translateY(-1.2px) rotate(.4deg)}}
.wms-login .leg-l{transform-origin:0 32px;animation:wmsStepL .55s ease-in-out infinite;}
.wms-login .leg-r{transform-origin:0 32px;animation:wmsStepR .55s ease-in-out infinite;}
@keyframes wmsStepL{0%,100%{transform:rotate(14deg)}50%{transform:rotate(-14deg)}}
@keyframes wmsStepR{0%,100%{transform:rotate(-14deg)}50%{transform:rotate(14deg)}}
.wms-login .lift-arm{transform-origin:4px 16px;animation:wmsLiftArm 3.2s ease-in-out infinite;}
@keyframes wmsLiftArm{0%,100%{transform:rotate(-10deg)}50%{transform:rotate(-90deg)}}
.wms-login .lift-torso{transform-origin:0 36px;animation:wmsLiftTorso 3.2s ease-in-out infinite;}
@keyframes wmsLiftTorso{0%,100%{transform:rotate(0deg) translateY(0)}50%{transform:rotate(-3deg) translateY(-2px)}}
.wms-login .stack-arm{transform-origin:4px 16px;animation:wmsStackArm 2.4s ease-in-out infinite;}
@keyframes wmsStackArm{0%,100%{transform:rotate(-20deg)}50%{transform:rotate(-110deg)}}
.wms-login .conveyor-belt-stripe{animation:wmsConveyor 2.5s linear infinite;}
@keyframes wmsConveyor{from{transform:translateX(0)}to{transform:translateX(-32px)}}
.wms-login[data-theme="light"] .scene-fill-floor{fill:#b8c2d2!important;}
.wms-login[data-theme="light"] .scene-fill-floor-2{fill:#a3afc4!important;}
.wms-login[data-theme="light"] .scene-fill-rack{fill:#4a5470!important;}
.wms-login[data-theme="light"] .scene-fill-rack-dark{fill:#353d54!important;}
.wms-login[data-theme="light"] .scene-fill-wall{fill:#cfd7e3!important;}
.wms-login[data-theme="light"] .scene-fill-window{fill:#e8eef6!important;}
.wms-login[data-theme="light"] .scene-fill-roof{fill:#2c3447!important;}
.wms-login .login-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:flex-end;padding:32px clamp(24px,5vw,80px);z-index:5;}
.wms-login .card{width:440px;max-width:100%;padding:32px;border-radius:22px;background:var(--card-bg);backdrop-filter:blur(22px) saturate(140%);-webkit-backdrop-filter:blur(22px) saturate(140%);border:1px solid var(--card-border);box-shadow:var(--shadow-card),inset 0 1px 0 var(--card-highlight),inset 0 0 0 1px rgba(255,255,255,.02);position:relative;transition:background 320ms ease,border-color 320ms ease,box-shadow 320ms ease,transform 320ms ease;}
.wms-login .card.shake{animation:wmsShake .42s cubic-bezier(.36,.07,.19,.97) both;}
@keyframes wmsShake{10%,90%{transform:translateX(-2px)}20%,80%{transform:translateX(4px)}30%,50%,70%{transform:translateX(-8px)}40%,60%{transform:translateX(8px)}}
.wms-login .card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;gap:12px;}
.wms-login .lang-switch{display:inline-flex;background:var(--chip-bg);border:1px solid var(--field-border);border-radius:999px;padding:3px;gap:2px;}
.wms-login .lang-switch button{appearance:none;border:0;background:transparent;color:var(--fg-muted);padding:6px 12px;font-size:12px;font-weight:600;letter-spacing:.02em;border-radius:999px;cursor:pointer;font-family:inherit;transition:color 200ms ease,background 200ms ease,box-shadow 200ms ease;}
.wms-login .lang-switch button.active{color:#fff;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));box-shadow:0 4px 12px -3px rgba(249,115,22,.55),inset 0 1px 0 rgba(255,255,255,.25);}
.wms-login .lang-switch button:hover:not(.active){color:var(--fg);}
.wms-login .theme-toggle{appearance:none;border:1px solid var(--field-border);background:var(--chip-bg);border-radius:999px;width:56px;height:30px;position:relative;cursor:pointer;transition:background 200ms ease,border-color 200ms ease;flex-shrink:0;}
.wms-login .theme-toggle::before{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:999px;background:linear-gradient(180deg,#fff,#d8def0);box-shadow:0 2px 6px rgba(0,0,0,.25);transition:transform 320ms cubic-bezier(.5,1.4,.4,1),background 320ms ease;}
.wms-login[data-theme="light"] .theme-toggle::before{transform:translateX(26px);background:linear-gradient(180deg,var(--amber-400),var(--orange-500));}
.wms-login .theme-toggle .icon-moon,.wms-login .theme-toggle .icon-sun{position:absolute;top:50%;transform:translateY(-50%);width:12px;height:12px;color:var(--fg-muted);}
.wms-login .theme-toggle .icon-moon{right:8px;}.wms-login .theme-toggle .icon-sun{left:8px;}
.wms-login .brand{display:flex;flex-direction:column;align-items:flex-start;gap:10px;margin-bottom:22px;}
.wms-login .brand-logo{background:#fff;border-radius:14px;padding:10px 16px;display:inline-flex;align-items:center;box-shadow:0 8px 22px -10px rgba(0,0,0,.45),inset 0 1px 0 rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.12);}
.wms-login .brand-logo img{height:44px;width:auto;display:block;}
.wms-login .brand-system{font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--fg-muted);}
.wms-login .heading{font-size:28px;font-weight:700;letter-spacing:-.025em;line-height:1.1;margin:0 0 6px;color:var(--fg);}
.wms-login .sub{font-size:13.5px;color:var(--fg-muted);margin:0 0 22px;line-height:1.5;}
.wms-login form{display:flex;flex-direction:column;gap:14px;}
.wms-login .field-label{display:block;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--fg-muted);margin-bottom:6px;}
.wms-login .field{position:relative;display:flex;align-items:center;background:var(--field-bg);border:1px solid var(--field-border);border-radius:10px;transition:border-color 200ms ease,box-shadow 220ms ease,background 200ms ease;}
.wms-login .field:focus-within{border-color:var(--field-border-focus);box-shadow:0 0 0 4px rgba(249,115,22,.18),0 0 24px -4px rgba(249,115,22,.35);}
.wms-login .field.field-error{border-color:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.15);}
.wms-login .field .lead-icon{width:38px;height:44px;display:grid;place-items:center;color:var(--fg-dim);flex-shrink:0;}
.wms-login .field:focus-within .lead-icon{color:var(--orange-500);}
.wms-login .field input{flex:1;border:0;background:transparent;outline:none;color:var(--fg);font:inherit;font-size:14px;padding:12px 4px;min-width:0;}
.wms-login .field input::placeholder{color:var(--fg-dim);}
/* ── autofill override — prevent browser white background ── */
.wms-login .field input:-webkit-autofill,
.wms-login .field input:-webkit-autofill:hover,
.wms-login .field input:-webkit-autofill:focus {
    -webkit-text-fill-color:var(--fg);
    -webkit-box-shadow:0 0 0 1000px rgba(13,17,23,.75) inset;
    transition:background-color 5000s ease-in-out 0s;
    caret-color:var(--fg);
}
.wms-login[data-theme="light"] .field input:-webkit-autofill,
.wms-login[data-theme="light"] .field input:-webkit-autofill:hover,
.wms-login[data-theme="light"] .field input:-webkit-autofill:focus {
    -webkit-text-fill-color:var(--fg);
    -webkit-box-shadow:0 0 0 1000px rgba(255,255,255,.9) inset;
    transition:background-color 5000s ease-in-out 0s;
    caret-color:var(--fg);
}
.wms-login .eye-toggle{appearance:none;border:0;background:transparent;color:var(--fg-dim);cursor:pointer;padding:0 12px;height:44px;display:grid;place-items:center;}
.wms-login .eye-toggle:hover{color:var(--fg);}
.wms-login .row{display:flex;justify-content:space-between;align-items:center;margin-top:4px;}
.wms-login .check{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--fg-muted);cursor:pointer;user-select:none;}
.wms-login .check input{display:none;}
.wms-login .check .box{width:16px;height:16px;border-radius:4px;border:1.5px solid var(--field-border);background:var(--field-bg);display:grid;place-items:center;transition:border-color 200ms ease,background 200ms ease;}
.wms-login .check .box svg{width:11px;height:11px;color:#fff;opacity:0;transform:scale(.6);transition:opacity 180ms ease,transform 180ms ease;}
.wms-login .check input:checked+.box{background:var(--orange-500);border-color:var(--orange-500);}
.wms-login .check input:checked+.box svg{opacity:1;transform:scale(1);}
.wms-login .link{font-size:13px;color:var(--blue-400);text-decoration:none;font-weight:500;transition:color 200ms ease;}
.wms-login[data-theme="light"] .link{color:var(--blue-700);}
.wms-login .link:hover{color:var(--orange-500);}
.wms-login .btn{margin-top:6px;width:100%;height:48px;border-radius:12px;border:0;cursor:pointer;color:#fff;font:inherit;font-size:14px;font-weight:700;letter-spacing:.02em;background:linear-gradient(180deg,var(--orange-400) 0%,var(--orange-500) 60%,#ea5a0c 100%);box-shadow:0 8px 22px -6px rgba(249,115,22,.55),inset 0 1px 0 rgba(255,255,255,.25),inset 0 -1px 0 rgba(0,0,0,.12);position:relative;overflow:hidden;transition:transform 200ms ease,box-shadow 220ms ease,filter 200ms ease;}
.wms-login .btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 14px 30px -6px rgba(249,115,22,.65),inset 0 1px 0 rgba(255,255,255,.28);}
.wms-login .btn:active:not(:disabled){transform:translateY(0);filter:brightness(.95);}
.wms-login .btn:disabled{cursor:progress;filter:saturate(.9);}
.wms-login .btn .label{display:inline-flex;align-items:center;gap:10px;justify-content:center;}
.wms-login .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:999px;display:inline-block;animation:wmsSpin .9s linear infinite;}
.wms-login .error{display:none;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fda4a4;font-size:13px;margin-top:10px;}
.wms-login[data-theme="light"] .error{color:#b42318;}
.wms-login .error.show{display:flex;}
.wms-login .divider{height:1px;background:var(--divider);margin:22px 0 14px;}
.wms-login .foot{display:flex;align-items:center;justify-content:space-between;font-size:11.5px;color:var(--fg-dim);font-family:"JetBrains Mono",ui-monospace,monospace;letter-spacing:.02em;}
.wms-login .status{display:inline-flex;align-items:center;gap:8px;}
.wms-login .dot{width:7px;height:7px;border-radius:999px;background:var(--emerald);animation:wmsPulse 2.2s ease-in-out infinite;}
@keyframes wmsPulse{0%{box-shadow:0 0 0 0 rgba(16,185,129,.55)}70%{box-shadow:0 0 0 8px rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}
.wms-login .sys-tag{position:absolute;top:32px;left:32px;z-index:5;display:inline-flex;align-items:center;gap:10px;padding:8px 14px;border-radius:999px;background:var(--card-bg);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--card-border);font-size:11px;color:var(--fg-muted);font-family:"JetBrains Mono",ui-monospace,monospace;letter-spacing:.08em;text-transform:uppercase;}
.wms-login .sys-tag .pip{width:6px;height:6px;border-radius:2px;background:var(--orange-500);box-shadow:0 0 8px var(--orange-500);}
@media(max-width:860px){.wms-login .login-wrap{justify-content:center;}.wms-login .card{width:100%;max-width:440px;}.wms-login .sys-tag{display:none;}}
</style>
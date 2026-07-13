# -*- mode: python ; coding: utf-8 -*-


a = Analysis(
    ['ras_agent_main.py'],
    pathex=[],
    binaries=[],
    datas=[('ras_agent', 'ras_agent'), ('service', 'service')],
    hiddenimports=['psutil', 'requests', 'schedule', 'win32service', 'win32serviceutil', 'win32con', 'win32api', 'win32event', 'pywintypes'],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='ras_agent',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=True,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)

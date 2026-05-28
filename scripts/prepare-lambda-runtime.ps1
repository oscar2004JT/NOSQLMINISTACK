$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$runtimeDir = Join-Path $root "lambda-runtime"
$imageName = "ecommerce-local-php-runtime:local"

Set-Location $root

docker build -t $imageName -f docker/localstack-php-runtime/Dockerfile .

if (Test-Path $runtimeDir) {
    Remove-Item -Recurse -Force $runtimeDir
}

New-Item -ItemType Directory -Force `
    (Join-Path $runtimeDir "bin"), `
    (Join-Path $runtimeDir "lib64") | Out-Null

$containerId = docker create --entrypoint sh $imageName

try {
    docker cp "${containerId}:/usr/bin/php" (Join-Path $runtimeDir "bin/php")

    $libraries = @{
        "/lib64/ld-linux-x86-64.so.2" = "ld-linux-x86-64.so.2"
        "/lib64/libc.so.6" = "libc.so.6"
        "/lib64/libm.so.6" = "libm.so.6"
        "/lib64/libcrypt.so.2.0.0" = "libcrypt.so.2"
        "/lib64/libxml2.so.2.10.4" = "libxml2.so.2"
        "/lib64/libssl.so.3.5.5" = "libssl.so.3"
        "/lib64/libcrypto.so.3.5.5" = "libcrypto.so.3"
        "/lib64/libpcre2-8.so.0.11.0" = "libpcre2-8.so.0"
        "/lib64/libz.so.1.2.11" = "libz.so.1"
        "/lib64/libedit.so.0.0.67" = "libedit.so.0"
        "/lib64/libgcc_s-14-20250110.so.1" = "libgcc_s.so.1"
        "/lib64/liblzma.so.5.2.5" = "liblzma.so.5"
        "/lib64/libtinfo.so.6.6" = "libtinfo.so.6"
    }

    foreach ($source in $libraries.Keys) {
        docker cp "${containerId}:$source" (Join-Path $runtimeDir "lib64/$($libraries[$source])")
    }
}
finally {
    docker rm $containerId | Out-Null
}

Write-Host "Lambda PHP runtime listo en $runtimeDir"

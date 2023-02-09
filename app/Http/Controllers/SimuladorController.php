<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SimuladorController extends Controller
{
    private $dadosSimulador;
    private $simulacao = [];

    public function simular(Request $request)
    {
        $this->carregarArquivoDadosSimulador()
            ->simularEmprestimo($request->valor_emprestimo)
            ->filtrarConvenio($request->convenios)
            ->filtrarInstituicao($request->instituicoes)
            ->filtrarParcelas($request->parcelas);

        return \response()->json($this->simulacao);
    }

    private function carregarArquivoDadosSimulador(): self
    {
        $this->dadosSimulador = json_decode(\File::get(storage_path("app/public/simulador/taxas_instituicoes.json")));
        return $this;
    }

    private function simularEmprestimo(float $valorEmprestimo): self
    {
        foreach ($this->dadosSimulador as $dados) {
            $this->simulacao[$dados->instituicao][] = [
                "taxa"            => $dados->taxaJuros,
                "parcelas"        => $dados->parcelas,
                "valor_parcela"    => $this->calcularValorDaParcela($valorEmprestimo, $dados->coeficiente),
                "convenio"        => $dados->convenio,
            ];
        }
        return $this;
    }

    private function calcularValorDaParcela(float $valorEmprestimo, float $coeficiente): float
    {
        return round($valorEmprestimo * $coeficiente, 2);
    }

    private function filtrarInstituicao(array $instituicoes): self
    {

        $instituicoes = array_map(function ($instituicao) {
            return strtoupper($instituicao);
        }, $instituicoes);

        if (\count($instituicoes)) {
            $arrayAux = [];
            foreach ($instituicoes as $key => $instituicao) {
                if (\array_key_exists($instituicao, $this->simulacao)) {
                    $arrayAux[$instituicao] = $this->simulacao[$instituicao];
                }
            }
            $this->simulacao = $arrayAux;
        }
        return $this;
    }

    private function filtrarConvenio(array $convenios): self
    {

        $convenios = array_map(function ($convenio) {
            return strtoupper($convenio);
        }, $convenios);

        if (\count($convenios)) {
            $aux = [];
            foreach ($this->simulacao as $key => $taxas) {

                $taxasFiltradas = array_filter($taxas, function ($taxa) use ($convenios) {
                    return in_array($taxa["convenio"], $convenios);
                });

                if (!empty($taxasFiltradas)) {

                    if (!key_exists($key, $aux)) {
                        $aux[$key] = $taxasFiltradas;
                    }

                    $this->simulacao = $aux;
                }
            }
        }
        return $this;
    }

    private function filtrarParcelas(int $parcelas): self
    {
        if ($parcelas) {
            $aux = [];
            foreach ($this->simulacao as $key => $taxas) {

                $taxasFiltradas = array_filter($taxas, function ($taxa) use ($parcelas) {
                    return $taxa['parcelas'] == $parcelas;
                });

                if (!empty($taxasFiltradas)) {
                    if (!key_exists($key, $aux)) {
                        $aux[$key] = $taxasFiltradas;
                    }

                    $this->simulacao = $aux;
                }
            }
            $this->simulacao = $aux;
        }

        return $this;
    }
}
